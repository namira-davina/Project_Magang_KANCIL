
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('formTambahKategori');

    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        const nama = document.getElementById('namaKategori').value.trim();

        if (!nama) {
            event.preventDefault();
            alert('Nama kategori wajib diisi.');
            return;
        }

        // Biarkan form submit secara normal ke server
    });

});
