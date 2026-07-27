<?php
function confirmDelete(string $message = 'Are you sure you want to delete this item?'): string {
    return 'onsubmit="return confirm(' . htmlspecialchars(json_encode($message), ENT_QUOTES, 'UTF-8') . ');"';
}
