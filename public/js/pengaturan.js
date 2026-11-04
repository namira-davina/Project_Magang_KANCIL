document.addEventListener('DOMContentLoaded', function () {
    const btnSimpanAkun = document.getElementById('btnSimpanAkun');
    const btnSimpanChatbot = document.getElementById('btnSimpanChatbot');
    const btnUbahPassword = document.getElementById('btnUbahPassword');

    if (btnSimpanAkun) {
        btnSimpanAkun.addEventListener('click', function () {
            alert('Perubahan profil pegawai berhasil disimpan.');
        });
    }

    if (btnSimpanChatbot) {
        btnSimpanChatbot.addEventListener('click', function () {
            alert('Pengaturan chatbot berhasil disimpan.');
        });
    }

    if (btnUbahPassword) {
        btnUbahPassword.addEventListener('click', function () {
            const passwordLama = document.getElementById('passwordLama')?.value || '';
            const passwordBaru = document.getElementById('passwordBaru')?.value || '';
            const konfirmasi = document.getElementById('konfirmasiPassword')?.value || '';

            if (!passwordLama || !passwordBaru || !konfirmasi) {
                alert('Mohon lengkapi semua kolom kata sandi.');
                return;
            }

            if (passwordBaru !== konfirmasi) {
                alert('Konfirmasi kata sandi baru tidak cocok.');
                return;
            }

            alert('Kata sandi berhasil diperbarui.');
            document.getElementById('passwordLama').value = '';
            document.getElementById('passwordBaru').value = '';
            document.getElementById('konfirmasiPassword').value = '';
        });
    }
});