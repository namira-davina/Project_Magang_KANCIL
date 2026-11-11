<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom dan tabel untuk integrasi WhatsApp (BalesOtomatis), mesin bot,
 * AI (LLM), matriks eskalasi, dan role Pimpinan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'petugas', 'pimpinan'])->default('petugas')->change();
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');   // PSP-001 dst.
            $table->string('menu_path', 30)->nullable()->after('code');      // 0>1>1
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->string('ticket_number', 30)->nullable()->change();
            $table->string('whatsapp_number', 25)->nullable()->change();
            $table->string('wa_chat_id', 100)->nullable()->unique()->after('id');
            $table->string('device_key', 100)->nullable();
            $table->json('session_state')->nullable();
            $table->dateTime('last_inbound_at')->nullable();
            $table->dateTime('bot_paused_until')->nullable();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('external_message_id', 150)->nullable()->unique();
            $table->string('client_uuid', 64)->nullable()->index();
            $table->enum('delivery_status', ['queued', 'sent', 'failed', 'delivered', 'read'])->nullable();
            $table->text('delivery_error')->nullable();
            $table->json('meta')->nullable();
            $table->json('raw_payload')->nullable();
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->enum('priority', ['Tinggi', 'Sedang', 'Rendah'])->nullable()->index();
            $table->unsignedTinyInteger('escalation_level')->default(1);
            $table->json('attachments')->nullable();
            $table->dateTime('first_response_at')->nullable();
        });

        Schema::table('escalation_logs', function (Blueprint $table) {
            $table->string('escalation_code', 5)->nullable()->index();
            $table->foreignId('faq_id')->nullable()->constrained('faqs')->nullOnDelete();
            $table->enum('priority', ['Tinggi', 'Sedang', 'Rendah'])->nullable();
            $table->string('menu_path', 30)->nullable();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('number_key', 100)->nullable();
            $table->string('message_id', 150)->nullable()->index();
            $table->string('dedupe_key', 64)->nullable()->unique();
            $table->json('payload');
            $table->dateTime('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('llm_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('model', 100)->nullable();
            $table->text('question');
            $table->longText('answer')->nullable();
            $table->json('faq_codes')->nullable();
            $table->boolean('confident')->default(false);
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_logs');
        Schema::dropIfExists('webhook_events');

        Schema::table('escalation_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('faq_id');
            $table->dropIndex(['escalation_code']);
            $table->dropColumn(['escalation_code', 'priority', 'menu_path']);
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['priority']);
            $table->dropColumn(['priority', 'escalation_level', 'attachments', 'first_response_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['external_message_id']);
            $table->dropIndex(['client_uuid']);
            $table->dropColumn(['external_message_id', 'client_uuid', 'delivery_status', 'delivery_error', 'meta', 'raw_payload']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['wa_chat_id']);
            $table->dropColumn(['wa_chat_id', 'device_key', 'session_state', 'last_inbound_at', 'bot_paused_until']);
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'menu_path']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'petugas'])->default('petugas')->change();
        });
    }
};
