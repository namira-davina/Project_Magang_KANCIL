// E2E dashboard untuk temuan audit 20 Nov 2026 (PDF 4 kategori pengaduan + temuan dashboard).
// Data: database/seeders/E2eSeeder.php. Login admin/password.
import { expect, test } from '@playwright/test';

async function login(page) {
    await page.goto('/antrean');
    if (!page.url().includes('/login')) return; // sesi masih aktif

    await page.goto('/login');
    await page.locator('#username').fill('admin');
    await page.locator('#password').fill('password');
    await page.locator('button[type="submit"]').click();
    await expect(page).not.toHaveURL(/\/login$/);
}

/** Terima alert/confirm berikutnya dan kembalikan teksnya. */
function nextDialog(page) {
    return new Promise((resolve) => {
        page.once('dialog', async (dialog) => {
            const message = dialog.message();
            await dialog.accept();
            resolve(message);
        });
    });
}

test.describe.serial('Audit 20 Nov: antrean → pengaduan → tindak lanjut', () => {
    // Satu sesi login untuk seluruh skenario berurutan (tidak memicu throttle login 10/menit).
    let shared;
    test.beforeAll(async ({ browser }) => {
        shared = await browser.newPage();
        await login(shared);
    });
    test.afterAll(async () => shared?.close());

    test('antrean: rata-rata tunggu tidak tergelembung tiket kemarin', async () => {
        const page = shared;
        await login(page);
        await page.goto('/antrean');

        // Tiket kemarin (diambil hari ini) tidak dihitung; satu-satunya tiket hari ini yang sudah diambil menunggu 2 menit.
        await expect(page.locator('#statRataTunggu')).toHaveText('2 mnt');
        await expect(page.getByText('Dari tiket masuk hari ini yang sudah diambil')).toBeVisible();
        await expect(page.locator('#statMenunggu')).toHaveText('5'); // 1 seeder + 4 percakapan simulasi (global-setup)
    });

    test('ambil tiket lalu jadikan pengaduan "Pelayanan petugas": teks jujur soal nomor ADU', async () => {
        const page = shared;
        await login(page);
        await page.goto('/antrean');

        const row = page.locator('#antreanTableBody tr', { hasText: 'Pemohon E2E Antre' });
        await expect(row).toBeVisible();

        // Ambil mengarahkan ke halaman percakapan.
        await row.getByRole('button', { name: 'Ambil' }).click();
        await expect(page).toHaveURL(/\/percakapan\?id=\d+/);

        await page.goto('/antrean');
        const handled = page.locator('#antreanTableBody tr', { hasText: 'Pemohon E2E Antre' });
        await handled.getByRole('button', { name: 'Jadikan Pengaduan' }).click();

        const dialog = page.locator('#dialogPengaduan');
        await expect(dialog).toBeVisible();
        await expect(dialog).toContainText('nomor ADU hanya untuk petugas');
        await expect(dialog).not.toContainText('pemohon menerima nomor pengaduan');
        await expect(page.locator('#dialogIsi')).toHaveValue('Petugas customer care kurang ramah saat memberi informasi');

        await page.locator('#dialogKategori').selectOption('3') // Pelayanan petugas (prioritas Tinggi);
        await page.locator('#dialogIsi').fill('Customer care kurang ramah');

        const alertText = nextDialog(page);
        await page.locator('#dialogSimpan').click();
        const message = await alertText;

        expect(message).toMatch(/^Pengaduan ADU-\d{6}-\d{4} dibuat\./);
        expect(message).toContain('Pemohon sudah diberi tahu bahwa permintaannya dicatat sebagai pengaduan resmi.');
        expect(message).not.toContain('Nomor pengaduan sudah dikirim');

        await expect(page.locator('#antreanTableBody tr', { hasText: 'Pemohon E2E Antre' })).toHaveCount(0);
    });

    test('pengaduan: label pagination benar, filter, dan pengaduan baru prioritas Tinggi', async () => {
        const page = shared;
        await login(page);
        await page.goto('/pengaduan');

        const info = page.locator('#pengaduanPaginationInfo');
        await expect(info).toHaveText('Menampilkan 1–10 dari 14 tiket');
        await expect(page.locator('#pengaduanTableBody tr')).toHaveCount(10);

        await page.locator('#pengaduanPagination').getByRole('button', { name: '2', exact: true }).click();
        await expect(info).toHaveText('Menampilkan 11–14 dari 14 tiket');
        await expect(page.locator('#pengaduanTableBody tr')).toHaveCount(4);

        await page.locator('#pengaduanStatusFilter').selectOption('Selesai');
        await page.locator('#btnFilterPengaduan').click();
        await expect(info).toHaveText('Menampilkan 1–4 dari 4 tiket (difilter dari 14)');

        await page.locator('#pengaduanStatusFilter').selectOption('');
        await page.locator('#pengaduanSearch').fill('Pemohon E2E Antre');
        await page.locator('#btnFilterPengaduan').click();
        const newRow = page.locator('#pengaduanTableBody tr', { hasText: 'Pemohon E2E Antre' });
        await expect(newRow).toHaveCount(1);
        await expect(newRow).toContainText('Pelayanan petugas');
        await expect(newRow).toContainText('Menunggu');

        await newRow.getByRole('button', { name: 'Detail' }).click();
        await expect(page.locator('#detailPrioritas')).toContainText('Tinggi');
    });

    test('pengaduan: Diproses lalu Selesai dengan tanggapan', async () => {
        const page = shared;
        await login(page);
        await page.goto('/pengaduan');
        await page.locator('#pengaduanSearch').fill('Pemohon E2E Antre');
        await page.locator('#btnFilterPengaduan').click();
        await page.locator('#pengaduanTableBody tr').first().getByRole('button', { name: 'Detail' }).click();

        for (const [status, response] of [['Diproses', 'Keluhan sedang kami tindak lanjuti.'], ['Selesai', 'Petugas sudah kami tegur, mohon maaf.']]) {
            await page.locator('#ubahStatusPengaduan').selectOption(status);
            await page.locator('#tanggapanPetugas').fill(response);
            const alertText = nextDialog(page);
            await page.locator('#simpanStatusPengaduan').click();
            expect(await alertText).toContain(`disimpan dengan status "${status}"`);
            await expect(page.locator('#detailStatusBadge')).toHaveText(status);
            await expect(page.locator('#detailTanggapanLama')).toContainText(response);
        }
    });

    test('FAQ: label pagination dari data nyata, tanpa tombol halaman palsu', async () => {
        const page = shared;
        await login(page);

        let unexpectedAlert = null;
        page.on('dialog', async (dialog) => {
            unexpectedAlert = dialog.message();
            await dialog.dismiss();
        });

        await page.goto('/faq');
        const info = page.locator('#faqDataInfo');
        await expect(info).toHaveText('Menampilkan 1 - 6 dari 65 entri');

        const pages = page.locator('#faqPagination .btn-page-num');
        await expect(pages).toHaveCount(11); // 65 FAQ / 6 per halaman
        await expect(page.locator('#faqPagination .pagination-ellipsis')).toHaveCount(0);

        await pages.last().click();
        await expect(info).toHaveText('Menampilkan 61 - 65 dari 65 entri');
        expect(unexpectedAlert).toBeNull();

        await page.locator('#faqStatusFilter').selectOption('Perlu Verifikasi');
        await expect(info).toHaveText('Menampilkan 1 - 3 dari 3 entri'); // PSP-013, PSP-015, PSP-016
        const visible = page.locator('#faqTableBody tr.faq-row:visible');
        await expect(visible).toHaveCount(3);
        await expect(visible.filter({ hasText: 'Apa syarat permohonan paspor beda data?' })).toHaveCount(1);
    });
});
