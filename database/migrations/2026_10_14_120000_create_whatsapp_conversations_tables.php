<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atendimento WhatsApp institucional: conversas e mensagens próprias, separadas do
 * histórico comercial de leads (lead_activities), que permanece intacto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            // Sessão do WAHA que recebeu a conversa: isola o atendimento das demais sessões do WAHA compartilhado.
            $table->string('session', 64);
            // JID do contato no WhatsApp (5551999999999@c.us ou um identificador @lid).
            $table->string('chat_id', 128);
            $table->string('phone', 20)->nullable();
            $table->string('name')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_page')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview')->nullable();
            $table->timestamps();

            $table->unique(['session', 'chat_id']);
            $table->index('last_message_at');
            $table->index('phone');
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);
            $table->text('body');
            $table->boolean('has_media')->default(false);
            // UNIQUE: o WAHA emite `message` e `message.any` para a mesma mensagem; o índice garante uma gravação só.
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('status', 20)->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_conversation_id', 'sent_at']);
            // Nome explícito: o gerado passa do limite de 64 caracteres do MySQL.
            $table->index(['whatsapp_conversation_id', 'direction', 'read_at'], 'whatsapp_messages_conversation_unread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
    }
};
