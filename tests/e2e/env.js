// Lingkungan E2E: SQLite terpisah, tanpa gateway WhatsApp/AI (tidak ada pesan yang benar-benar dikirim).
// Variabel proses menimpa nilai .env (Dotenv Laravel tidak menimpa env yang sudah ada).
import path from 'node:path';

export const PORT = 8077;
export const BASE_URL = `http://127.0.0.1:${PORT}`;
export const DB_FILE = path.resolve('database/e2e.sqlite');

export const E2E_ENV = {
    APP_ENV: 'local',
    APP_DEBUG: 'true',
    APP_URL: BASE_URL,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: DB_FILE,
    DB_URL: '',
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'array', // tidak berbagi cache (rate limit login) dengan dev
    QUEUE_CONNECTION: 'database', // pesan WA hanya masuk tabel jobs, tidak diproses
    BOT_REPLY_INLINE: 'false',
    BALESOTOMATIS_API_KEY: '',
    BALESOTOMATIS_NUMBER_ID: '',
    LLM_ENABLED: 'false',
    LLM_API_KEY: '',
    MAIL_MAILER: 'array',
};
