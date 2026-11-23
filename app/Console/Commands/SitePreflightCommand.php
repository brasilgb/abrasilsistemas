<?php

namespace App\Console\Commands;

use App\Models\SeoMeta;
use App\Models\Setting;
use App\Services\CompanyProfile;
use App\Services\Seo\SeoSettings;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conferência da publicação (ABRASIL-COMERCIAL-02 §7), para rodar no servidor. Só lê: não envia e-mail,
 * não grava nada e não mostra senhas. A parte de indexação é do seo:check.
 */
class SitePreflightCommand extends Command
{
    protected $signature = 'site:preflight';

    protected $description = 'Confere migrations, assets, ambiente, e-mail, fila, formulário e SEO gravado no banco';

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(Migrator $migrator, SeoSettings $settings, CompanyProfile $company): int
    {
        $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : []);
        $this->result($pending === [], 'Nenhuma migration pendente', 'Migrations pendentes (faça backup antes): '.implode(', ', $pending));

        $manifest = json_decode((string) @file_get_contents(public_path('build/manifest.json')), true) ?: [];
        $missing = array_filter(array_column($manifest, 'file'), fn (string $file) => ! is_file(public_path('build/'.$file)));
        $this->result($manifest !== [] && $missing === [], 'Assets compilados: '.count($manifest).' entradas no manifesto', 'Assets ausentes: rode npm run build ou reconstrua a imagem.');
        $this->result(! is_file(public_path('hot')), 'Sem servidor de desenvolvimento do Vite', 'public/hot existe: o site tentaria carregar os assets do Vite local.');
        $pages = count(glob(config('seo.prerender_path').'/*.json') ?: []);
        $this->result($pages > 0 && config('seo.prerender'), "HTML inicial de {$pages} páginas", 'Sem HTML pré-renderizado: as páginas dependem de JavaScript.', critical: false);

        $this->result(app()->isProduction(), 'APP_ENV=production', 'APP_ENV='.app()->environment().': em produção deve ser production (defina APP_ENV no .env da infra).');
        $this->result(! config('app.debug'), 'APP_DEBUG desligado', 'APP_DEBUG=true expõe detalhes internos em erros.');
        $this->result(filled(config('app.key')), 'APP_KEY definida', 'APP_KEY vazia.');
        $this->result(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL com HTTPS', 'APP_URL não usa HTTPS: '.config('app.url'));
        $this->result((bool) config('session.secure'), 'Cookie de sessão só por HTTPS', 'SESSION_SECURE_COOKIE não está ligado.', critical: false);

        $mailer = (string) config('mail.default');
        $this->result($mailer === 'smtp' && filled(config('mail.mailers.smtp.host')) && filled(config('mail.mailers.smtp.username')) && filled(config('mail.mailers.smtp.password')), 'E-mail por SMTP com credenciais preenchidas', "E-mail não configurado por completo (mailer {$mailer}): as solicitações são gravadas, mas a equipe não é avisada.");
        $this->result(filter_var(config('contact.email'), FILTER_VALIDATE_EMAIL) !== false, 'Avisos do formulário para '.config('contact.email'), 'CONTACT_EMAIL inválido.');

        if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
            $stuck = DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<', now()->subMinutes(10)->timestamp)->count();
            $this->result($stuck === 0, 'Fila sem tarefas paradas', "{$stuck} tarefa(s) paradas há mais de 10 minutos: confira o abrasilsistema-worker (os avisos por e-mail passam pela fila).");
        }

        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $this->result($failed === 0, 'Nenhuma tarefa com falha', "{$failed} tarefa(s) com falha: php artisan queue:failed.", critical: false);
        $this->result(Schema::hasColumns('leads', ['request_status', 'source_page', 'entry_page', 'utm_source', 'marketing_consent_at']), 'Formulário: colunas de Solicitações presentes', 'Faltam colunas em leads: o formulário não conseguiria gravar.');

        $stored = Setting::query()->where('key', Setting::SEO_SETTINGS)->exists();
        $this->line('  <fg=blue>i</> SEO no banco: configurações '.($stored ? 'salvas no painel (prevalecem sobre o código)' : 'no padrão do código').'; '.SeoMeta::query()->where('subject_type', SeoMeta::TYPE_PAGE)->count().' página(s) com metadados próprios (prevalecem sobre os novos títulos).');

        if ($stored) {
            foreach (['default_title', 'default_description', 'social_image_url'] as $key) {
                $this->result($settings->get($key) === SeoSettings::defaults()[$key], "SEO: {$key} igual ao padrão atual", "SEO: {$key} foi gravado no banco e difere do padrão atual; revise em Painel → SEO → Configurações.", critical: false);
            }
        }

        $profile = $company->toArray();
        $this->result(filled($profile['legalName']) && filled($profile['cnpj']), 'Razão social e CNPJ preenchidos', 'Razão social ou CNPJ não preenchidos (opcional: não são exibidos).', critical: false);

        $this->newLine();

        if ($this->failures > 0) {
            $this->error("{$this->failures} problema(s) e {$this->warnings} aviso(s). Corrija os problemas antes de divulgar.");

            return self::FAILURE;
        }

        $this->info("Pronto para publicar, com {$this->warnings} aviso(s). Rode também: php artisan seo:check --live");

        return self::SUCCESS;
    }

    private function result(bool $ok, string $label, string $problem, bool $critical = true): void
    {
        if ($ok) {
            $this->line("  <fg=green>✓</> {$label}");

            return;
        }

        $critical ? $this->failures++ : $this->warnings++;
        $this->line(($critical ? '  <fg=red>✗</> ' : '  <fg=yellow>!</> ').$problem);
    }
}
