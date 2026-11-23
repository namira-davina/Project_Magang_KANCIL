// E2E feedback Imigrasi 20 Nov sore: tiket Cek Status memuat tempat permohonan, jawaban Paspor Rusak dengan PNBP
// terpisah terlihat di monitoring percakapan, dan FAQ Beda Data (PSP-016) menunggu verifikasi.
// Data percakapan dibuat oleh mesin bot asli lewat `kancil:simulasi` di global-setup.js.
import { expect, test } from '@playwright/test';

test.describe.serial('Feedback Imigrasi 20 Nov sore', () => {
    let page;

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage();
        await page.goto('/login');
        await page.locator('#username').fill('admin');
        await page.locator('#password').fill('password');
        await page.locator('button[type="submit"]').click();
        await expect(page).not.toHaveURL(/\/login$/);
    });
    test.afterAll(async () => page?.close());

    test('antrean: tiket Cek Status menampilkan nomor dan tempat permohonan', async () => {
        await page.goto('/antrean');

        const row = page.locator('#antreanTableBody tr', { hasText: 'tempat permohonan MPP Banyumas' });
        await expect(row).toHaveCount(1);
        await expect(row).toContainText('Pelayanan Paspor WNI › Cek Status Paspor');
        await expect(row).toContainText('no. permohonan 1568000000012345');
    });

    test('percakapan: jawaban Paspor Rusak menyebut PNBP terpisah, tanpa angka gabungan', async () => {
        await page.goto('/antrean');
        const row = page.locator('#antreanTableBody tr', { hasText: 'Permohonan Paspor Rusak' });
        await expect(row).toHaveCount(1);
        await row.getByRole('link', { name: 'Buka Chat' }).click();
        await expect(page).toHaveURL(/\/percakapan\?id=\d+/);

        const messages = page.locator('#chatMessages');
        await expect(messages).toContainText('Ketik 9 Permohonan Paspor Beda Data');
        await expect(messages).toContainText('Ketik 12 untuk kembali ke menu sebelumnya');
        await expect(messages).toContainText('Daftar dan unggah dokumen melalui https://sipantas.kanimcilacap.com/');
        await expect(messages).toContainText('Paspor elektronik 5 tahun: Rp650.000');
        await expect(messages).toContainText('Paspor elektronik 10 tahun: Rp950.000');
        await expect(messages).toContainText('Denda paspor rusak: Rp500.000');
        await expect(messages).not.toContainText('1.150.000');
        await expect(messages).not.toContainText('1.450.000');
    });

    test('FAQ: Beda Data (PSP-016) terdaftar dan menunggu verifikasi', async () => {
        await page.goto('/faq');
        await page.locator('#faqSearch').fill('beda data');

        const row = page.locator('#faqTableBody tr.faq-row:visible', { hasText: 'Apa syarat permohonan paspor beda data?' });
        await expect(row).toHaveCount(1);
        await expect(row).toContainText('Perlu Verifikasi');
    });
});
