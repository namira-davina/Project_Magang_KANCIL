import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import { DB_FILE, E2E_ENV } from './env.js';

const env = { ...process.env, ...E2E_ENV };

function artisan(...args) {
    execFileSync('php', ['artisan', ...args], { env, stdio: 'inherit' });
}

/** Percakapan lewat mesin bot asli di kanal "simulasi" (tidak pernah dikirim ke WhatsApp). */
function chat(number, messages) {
    artisan('kancil:simulasi', messages[0], `--nomor=${number}`, '--reset');
    for (const message of messages.slice(1)) {
        artisan('kancil:simulasi', message, `--nomor=${number}`);
    }
}

/**
 * Database E2E segar: migrate:fresh + E2eSeeder (admin/password, antrean, 13 pengaduan, 65 FAQ),
 * lalu dua percakapan simulasi untuk feedback Imigrasi 20 Nov:
 *  - Cek Status Paspor dengan tempat permohonan (nomor → tempat 2 = MPP Banyumas) → tiket antrean.
 *  - Paspor Rusak (PNBP terpisah) lalu hubungi petugas → tiket antrean dengan riwayat jawaban.
 */
export default function globalSetup() {
    fs.writeFileSync(DB_FILE, '');
    artisan('migrate:fresh', '--force', '--seed', '--seeder=E2eSeeder');
    chat('6280000000071', ['Halo', '1', '11', '1498000000012345', '2']);
    chat('6280000000072', ['Halo', '1', '8', '1']);
    artisan('config:clear');
}
