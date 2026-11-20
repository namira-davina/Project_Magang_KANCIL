import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import { DB_FILE, E2E_ENV } from './env.js';

/** Database E2E segar: migrate:fresh + E2eSeeder (admin/password, antrean, 13 pengaduan, 65 FAQ). */
export default function globalSetup() {
    fs.writeFileSync(DB_FILE, '');
    execFileSync('php', ['artisan', 'migrate:fresh', '--force', '--seed', '--seeder=E2eSeeder'], {
        env: { ...process.env, ...E2E_ENV },
        stdio: 'inherit',
    });
    execFileSync('php', ['artisan', 'config:clear'], { env: { ...process.env, ...E2E_ENV }, stdio: 'inherit' });
}
