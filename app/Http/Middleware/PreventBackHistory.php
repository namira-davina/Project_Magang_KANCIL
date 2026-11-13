<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventBackHistory
{
    /**
     * Tambahkan header no-cache agar browser tidak menyimpan
     * halaman internal, sehingga tombol Back tidak bisa menampilkan
     * halaman protected setelah pengguna logout.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // headers->set() berlaku juga untuk respons unduhan (StreamedResponse/BinaryFileResponse).
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');

        return $response;
    }
}
