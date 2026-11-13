<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiket layanan petugas (antrean FIFO): dibuat saat pemohon minta dihubungkan ke petugas
 * (menu Hubungi Petugas, 88, atau AI tidak tahu jawaban). Nomor LYN-YYYYMMDD-NNN, nomor antrean harian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 30)->unique();
            $table->date('queue_date');
            $table->unsignedInteger('queue_no');
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('escalation_log_id')->nullable()->constrained('escalation_logs')->nullOnDelete();
            $table->string('escalation_code', 5)->nullable();
            $table->enum('priority', ['Tinggi', 'Sedang', 'Rendah'])->nullable();
            $table->string('menu_path', 30)->nullable();
            $table->text('question_text')->nullable();
            $table->enum('status', ['Menunggu', 'Ditangani', 'Selesai', 'Kedaluwarsa', 'Batal'])->default('Menunggu');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('queued_at');
            $table->dateTime('taken_at')->nullable();
            $table->dateTime('first_response_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('last_notice_at')->nullable();
            $table->string('close_reason')->nullable();
            $table->timestamps();

            $table->unique(['queue_date', 'queue_no']);
            $table->index(['status', 'queued_at', 'id']);
            $table->index(['conversation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_tickets');
    }
};
