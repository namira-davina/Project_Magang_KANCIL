<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Application tables, mirroring `kancil_sapa.sql`.
 * (`users` lives in the 0001_01_01_000000 migration.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $this->timestamps($table);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('faq_categories')->nullOnDelete()->cascadeOnUpdate();
            $table->text('question');
            $table->longText('answer');
            $table->string('source')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->enum('verification_status', ['draft', 'perlu_verifikasi', 'terverifikasi'])->default('draft')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->dateTime('verified_at')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $this->timestamps($table);
        });

        Schema::create('faq_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_id')->constrained('faqs')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('keyword')->index();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('complaint_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $this->timestamps($table);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 30)->unique();
            $table->string('whatsapp_number', 25)->index();
            $table->string('user_name', 100)->nullable();
            $table->enum('channel', ['whatsapp', 'simulasi'])->default('simulasi');
            $table->enum('conversation_mode', ['BOT_ACTIVE', 'WAITING_HUMAN', 'HUMAN_HANDLING', 'CLOSED'])
                ->default('BOT_ACTIVE')->index();
            $table->string('current_menu', 50)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('last_message_at')->nullable()->index();
            $table->dateTime('closed_at')->nullable();
            $this->timestamps($table);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('sender_type', ['user', 'bot', 'ai', 'petugas', 'system'])->index();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('message_type', ['text', 'image', 'document', 'audio', 'other'])->default('text');
            $table->longText('message_text')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->foreignId('faq_id')->nullable()->constrained('faqs')->nullOnDelete()->cascadeOnUpdate();
            $table->boolean('is_read')->default(false);
            $table->dateTime('sent_at')->useCurrent();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['conversation_id', 'sent_at'], 'idx_message_conversation_time');
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 30)->unique();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('category_id')->nullable()->constrained('complaint_categories')->nullOnDelete()->cascadeOnUpdate();
            $table->string('user_name', 100);
            $table->string('whatsapp_number', 25)->index();
            $table->longText('complaint_text');
            $table->enum('status', ['Menunggu', 'Diproses', 'Selesai'])->default('Menunggu')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->longText('response_text')->nullable();
            $table->dateTime('submitted_at')->useCurrent()->index();
            $table->dateTime('resolved_at')->nullable();
            $this->timestamps($table);
        });

        Schema::create('escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('escalation_type', ['faq_not_found', 'user_request', 'complaint', 'special_case', 'uncertain_answer'])->index();
            $table->text('reason')->nullable();
            $table->enum('status', ['Menunggu', 'Diambil', 'Selesai'])->default('Menunggu')->index();
            $table->dateTime('escalated_at')->useCurrent();
            $table->dateTime('taken_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('bot_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->unique();
            $table->text('setting_value')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $this->timestamps($table);
        });
    }

    public function down(): void
    {
        foreach ([
            'bot_settings', 'escalation_logs', 'complaints', 'messages', 'conversations',
            'complaint_categories', 'faq_keywords', 'faqs', 'faq_categories',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }

    private function timestamps(Blueprint $table): void
    {
        $table->timestamp('created_at')->nullable()->useCurrent();
        $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
    }
};
