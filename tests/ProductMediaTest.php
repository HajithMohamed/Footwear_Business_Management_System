<?php

use App\Services\ProductMedia;

$mediaDb = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$mediaDb->exec('CREATE TABLE product_media (path TEXT PRIMARY KEY, product_id INTEGER, mime_type TEXT, contents BLOB)');
$writer = new ProductMedia($mediaDb);
$photoPath = 'products/7/original/' . str_repeat('a', 32) . '.png';
$photoBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
$photoFile = tempnam(sys_get_temp_dir(), 'product-media-');
try {
    file_put_contents($photoFile, $photoBytes);
    $writer->store($photoPath, $photoFile);
    $writer->store($photoPath, $photoFile);
    eq(1, $mediaDb->query('SELECT COUNT(*) FROM product_media')->fetchColumn(), 'Archiving the same photo twice preserves one copy');
    unlink($photoFile);
    $reader = new ProductMedia($mediaDb);
    ok($reader->exists($photoPath), 'A separate reader finds the photo after the local file disappears');
    ok($photoBytes === $reader->read($photoPath)['contents'], 'Durable photo bytes survive loss of the local upload');
    eq('image/png', $reader->read($photoPath)['mime_type'], 'Stored image retains its detected MIME type');
    ok($reader->read('../.env') === null, 'Media reads reject traversal paths');
    ok(!ProductMedia::validPath('products/7/original/../../secret.jpg'), 'Media rejects paths outside generated product photos');
    $reader->delete($photoPath);
    ok(!$writer->exists($photoPath), 'Deleting a photo removes its shared copy');
} finally {
    if (is_file($photoFile)) unlink($photoFile);
}
