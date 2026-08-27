<?php
// Shared Stock In / Stock Out / Transfers payload. Include once on each page
// inside a <script> block, after loading $items (the active inventory rows).
// It assigns window.STOCK_ITEMS. Note: this partial emits JS only (no
// <script> tags) so it can be inlined into a surrounding script block.
?>
    window.STOCK_ITEMS = <?php echo json_encode(array_map(function ($it) {
        return [
            'id'          => (int)$it['id'],
            'name'        => $it['name'],
            'brand'       => $it['brand'],
            'serial'      => $it['serial'],
            'quantity'    => (int)$it['quantity'],
            'location'    => $it['location'],
            'barcode'     => $it['barcode'],
            'qr'          => $it['qr'],
            'status'      => $it['status'],
            'photo'       => $it['photo'] ? BASE_URL . '/' . $it['photo'] : '',
        ];
    }, $items)); ?>;
