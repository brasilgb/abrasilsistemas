<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $categories = [
        'gestao-e-produtividade' => 'Gestão e produtividade',
        'tecnologia-e-inovacao' => 'Tecnologia e inovação',
        'sites-e-presenca-digital' => 'Sites e presença digital',
        'sistemas-e-automacao' => 'Sistemas e automação',
        'bastidores-ab-sistemas' => 'Bastidores AB Sistemas',
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->categories as $slug => $name) {
            DB::table('blog_categories')->insertOrIgnore([
                'name' => $name,
                'slug' => $slug,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('blog_categories')
            ->whereIn('slug', array_keys($this->categories))
            ->delete();
    }
};
