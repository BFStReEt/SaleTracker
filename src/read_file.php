<?php
if (!function_exists('readTextFile')) {
    function readTextFile($filePath) {
        if (!file_exists($filePath)) {
            return "Lỗi: Tệp không tồn tại.";
        }
        return file_get_contents($filePath);
    }
}
?>
