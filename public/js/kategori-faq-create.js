
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('formTambahKategori');

    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {

        event.preventDefault();

        const nama = document.getElementById('namaKategori').value.trim();

        if (!nama) {
            alert('Nama kategori wajib diisi.');
            return;
        }

        alert(
            'Tampilan tambah kategori sudah berjalan. ' +
            'Penyimpanan kategori belum dihubungkan ke database.'
        );

    });

});
