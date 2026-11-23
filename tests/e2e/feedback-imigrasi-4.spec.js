// E2E feedback Imigrasi 23 Nov (feedback user 4): navigasi "9 = menu sebelumnya" setelah jawaban, daftar persyaratan
// Paspor Baru + bukti permohonan M-Paspor, ANK-005 bermeterai, contoh nomor permohonan 1568.
// Percakapan dibuat oleh mesin bot asli lewat `kancil:simulasi` di global-setup.js.
import { expect, test } from '@playwright/test';

test.describe.serial('Feedback Imigrasi 23 Nov', () => {
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

    async function openChatFromQueue(topic) {
        await page.goto('/antrean');
        const row = page.locator('#antreanTableBody tr', { hasText: topic });
        await expect(row).toHaveCount(1);
        await row.getByRole('link', { name: 'Buka Chat' }).click();
        await expect(page).toHaveURL(/\/percakapan\?id=\d+/);

        return page.locator('#chatMessages');
    }

    test('menu 2: setelah jawaban tarif, 9 kembali ke daftar tarif lalu ITK bisa dipilih langsung', async () => {
        const messages = await openChatFromQueue('Izin Tinggal Kunjungan');

        await expect(messages).toContainText('Ketik 9 untuk kembali ke menu sebelumnya');
        await expect(messages).toContainText('Ketik 0 untuk kembali ke menu utama');
        await expect(messages).not.toContainText('Ketik 0 untuk kembali ke menu sebelumnya');
        await expect(messages).toContainText('Berapa tarif izin masuk kembali');
        await expect(messages).toContainText('Berapa tarif Izin Tinggal Kunjungan');

        // Urutan: jawaban Re-entry → submenu tarif (bukan menu utama) → jawaban ITK.
        const text = (await messages.innerText()).replace(/\s+/g, ' ');
        const reentry = text.indexOf('Berapa tarif izin masuk kembali');
        const submenu = text.indexOf('Ketik 7 untuk kembali ke menu sebelumnya', reentry);
        const itk = text.indexOf('Berapa tarif Izin Tinggal Kunjungan', reentry);
        expect(reentry).toBeGreaterThan(-1);
        expect(submenu).toBeGreaterThan(reentry);
        expect(itk).toBeGreaterThan(submenu);
        expect(text.slice(reentry, itk)).not.toContain('Selamat Datang');
    });

    test('Paspor Baru: daftar persyaratan dengan bukti permohonan M-Paspor dan PNBP terpisah', async () => {
        const messages = await openChatFromQueue('Persyaratan Paspor Baru');

        await expect(messages).toContainText('Persyaratan:');
        await expect(messages).toContainText('• KTP elektronik');
        await expect(messages).toContainText('• Akta lahir / ijazah / buku nikah');
        await expect(messages).toContainText('• Bukti permohonan M-Paspor');
        await expect(messages).toContainText('Paspor elektronik 5 tahun: Rp650.000');
        await expect(messages).not.toContainText('dan salah satu dari akta lahir/ijazah/buku nikah');
    });

    test('FAQ: PSP-001 dan ANK-005 menampilkan isi baru', async () => {
        await page.goto('/faq');
        await page.locator('#faqSearch').fill('bermeterai jika kedua orang tua');
        await expect(page.locator('#faqTableBody tr.faq-row:visible', {
            hasText: 'Siapa yang menandatangani surat pernyataan bermeterai jika kedua orang tua bercerai hidup?',
        })).toHaveCount(1);

        await page.locator('#faqSearch').fill('persyaratan untuk membuat paspor baru');
        const row = page.locator('#faqTableBody tr.faq-row:visible', { hasText: 'Apa saja persyaratan untuk membuat paspor baru?' });
        await expect(row).toHaveCount(1);
        await row.locator('a[title="Edit FAQ"]').click();
        await expect(page.locator('#jawaban')).toHaveValue(/• Bukti permohonan M-Paspor\nBawa berkas asli dan fotokopi ukuran A4\./);
    });

    test('Cek Status: contoh nomor permohonan berawalan 1568', async () => {
        const messages = await openChatFromQueue('tempat permohonan MPP Banyumas');

        await expect(messages).toContainText('Contoh: 1568000000012345');
        await expect(messages).not.toContainText('1498');
    });
});
