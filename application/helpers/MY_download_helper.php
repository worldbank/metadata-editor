<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}
/**
 * CodeIgniter
 *
 * @package    CodeIgniter
 * @subpackage Helpers
 * @category   Helpers
 */

// ------------------------------------------------------------------------

/**
 * CodeIgniter Download Helpers
 *
 * @package    CodeIgniter
 * @subpackage Helpers
 * @category   Helpers
 */

// ------------------------------------------------------------------------

/**
 * Force Download
 *
 * Generates headers that force a download to happen
 *
 * @param  string       $filename       Filename (display name) or full file path if $data === false
 * @param  string|false $data           Binary string to send; false to read from disk path in $filename
 * @param  bool         $enable_partial Enable HTTP Range / resumable downloads
 * @param  int          $speedlimit     KB/sec (0 = unlimited)
 * @return bool
 */
if (! function_exists('force_download2')) {
    function force_download2($filename = '', $data = false, $enable_partial = true, $speedlimit = 0)
    {
        if ($filename === '') {
            return false;
        }

        $reading_from_disk = ($data === false);

        if ($reading_from_disk && !is_file($filename)) {
            return false;
        }

        // Determine extension (best-effort; not required)
        $extension = '';
        $dotpos = strrpos($filename, '.');
        if ($dotpos !== false) {
            $extension = substr($filename, $dotpos + 1);
        }

        // Load known mimes
        $mimes = [];
        if (defined('APPPATH')) {
            @include APPPATH . 'config/mimes.php';
        }
        if (!is_array($mimes)) {
            $mimes = [];
        }

        // Pick MIME (prefer finfo over extension)
        $mime = null;

        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                if ($reading_from_disk) {
                    $try = @finfo_file($f, $filename);
                } else {
                    $try = @finfo_buffer($f, $data);
                }
                if ($try) {
                    $mime = $try;
                }
                finfo_close($f);
            }
        }

        if ($mime === null) {
            // fallback to extension map
            $mime_entry = $extension !== '' ? ($mimes[$extension] ?? null) : null;
            if ($mime_entry !== null) {
                $mime = is_array($mime_entry) ? $mime_entry[0] : $mime_entry;
            } else {
                // conservative default
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $is_ie_or_opera = (strpos($ua, 'MSIE') !== false) || (strpos($ua, 'Opera') !== false);
                $mime = $is_ie_or_opera ? 'application/octetstream' : 'application/octet-stream';
            }
        }

        // Determine size and download name
        $size = $reading_from_disk ? @filesize($filename) : strlen($data);
        if ($size === false) {
            // Could not stat file
            return false;
        }
        $name = $reading_from_disk ? basename($filename) : $filename;

        // Sanitize filename for header and add UTF-8 fallback
        $downloadName = basename($name);
        // strip CR/LF/quotes to avoid header injection
        $downloadName = str_replace(["\r", "\n", '"'], '_', $downloadName);
        $disposition = 'attachment; filename="' . $downloadName . '"';
        if (function_exists('mb_detect_encoding') && mb_detect_encoding($downloadName, 'UTF-8', true)) {
            $disposition .= "; filename*=UTF-8''" . rawurlencode($downloadName);
        }

        // Clear output buffers and disable compression/buffering to avoid corruption
        while (ob_get_level() > 0) { @ob_end_clean(); 
        }
        @ini_set('zlib.output_compression', 'Off');
        @ini_set('output_buffering', 'Off');

        // Zero-byte files: send headers and finish cleanly
        if ((int)$size === 0) {
            if (headers_sent()) {
                error_log('Headers already sent; cannot start zero-byte download');
                return false;
            }
            header('Content-Type: ' . $mime, true);
            header('X-Content-Type-Options: nosniff', true);
            header('Content-Disposition: ' . $disposition, true);
            header('Accept-Ranges: bytes', true);
            header('Content-Length: 0', true);
            header('Cache-Control: private, no-transform, max-age=0', true);
            header('Pragma: private', true);
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT', true);
            return true;
        }

        // Range defaults
        $start = 0;
        $end   = $size - 1; // inclusive
        $length = $size;
        $is_partial = false;

        // Parse HTTP Range header (bytes=start-end | start- | -suffix)
        if ($enable_partial && isset($_SERVER['HTTP_RANGE'])) {
            if (preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $m)) {
                $s = $m[1];
                $e = $m[2];

                if ($s === '' && $e === '') {
                    // Invalid
                    if (!headers_sent()) {
                        header('HTTP/1.1 416 Range Not Satisfiable');
                        header("Content-Range: bytes */{$size}");
                    }
                    return false;
                }

                if ($s === '') {
                    // last N bytes: "-SUFFIX"
                    $suffix = (int)$e;
                    if ($suffix <= 0) { $suffix = 0;
                    }
                    if ($suffix >= $size) {
                        $start = 0;
                    } else {
                        $start = $size - $suffix;
                    }
                } else {
                    $start = (int)$s;
                }

                if ($e !== '') {
                    $end = (int)$e;
                } else {
                    $end = $size - 1;
                }

                // bounds check
                if ($start > $end || $start >= $size || $end >= $size) {
                    if (!headers_sent()) {
                        header('HTTP/1.1 416 Range Not Satisfiable');
                        header("Content-Range: bytes */{$size}");
                    }
                    return false;
                }

                $length = $end - $start + 1;
                $is_partial = true;
            }
        }

        // Before sending any headers, ensure we still can
        if (headers_sent()) {
            error_log('Headers already sent; cannot start download');
            return false;
        }

        // Send headers
        if ($is_partial) {
            header('HTTP/1.1 206 Partial Content', true);
            header("Content-Range: bytes {$start}-{$end}/{$size}", true);
            header('Content-Length: ' . $length, true);
        } else {
            header('Content-Length: ' . $size, true);
        }
        header('Content-Type: ' . $mime, true);
        header('X-Content-Type-Options: nosniff', true);
        header('Content-Disposition: ' . $disposition, true);
        header('Accept-Ranges: bytes', true);
        header('Cache-Control: private, no-transform, max-age=0', true);
        header('Pragma: private', true);
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT', true);

        // Open output stream once
        $out = fopen('php://output', 'wb');
        if ($out === false) {
            error_log('Cannot open php://output');
            return false;
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        // Chunk size (KB/s -> bytes)
        $chunk_bytes = ($speedlimit > 0) ? max(1024, (int)$speedlimit * 1024) : (512 * 1024);

        if ($reading_from_disk) {
            $fp = fopen($filename, 'rb');
            if ($fp === false) {
                fclose($out);
                return false;
            }

            if ($is_partial && $start > 0) {
                if (fseek($fp, $start, SEEK_SET) !== 0) {
                    fclose($fp);
                    fclose($out);
                    error_log("Cannot seek to {$start}");
                    return false;
                }
            }

            $remaining = $length;

            while ($remaining > 0 && connection_status() === 0) {
                $to_read = min($remaining, $chunk_bytes);
                $buffer  = fread($fp, $to_read);
                if ($buffer === false || $buffer === '') {
                    break; // read error or EOF
                }
                $written = fwrite($out, $buffer);
                if ($written === false) {
                    break;
                }
                $remaining -= $written;
                fflush($out);

                if ($speedlimit > 0) {
                    // throttle roughly once per second
                    sleep(1);
                }
            }

            fclose($fp);
            fclose($out);

            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }

            return true;
        }

        // String/buffer mode
        if ($is_partial) {
            $slice = substr($data, $start, $length);
            $send_len = strlen($slice);
            if ($speedlimit > 0) {
                $idx = 0;
                while ($idx < $send_len && connection_status() === 0) {
                    $to_write = min($chunk_bytes, $send_len - $idx);
                    $written  = fwrite($out, substr($slice, $idx, $to_write));
                    if ($written === false) { break;
                    }
                    $idx += $to_write;
                    fflush($out);
                    sleep(1);
                }
            } else {
                fwrite($out, $slice);
            }
        } else {
            if ($speedlimit > 0) {
                $send_len = $size;
                $idx = 0;
                while ($idx < $send_len && connection_status() === 0) {
                    $to_write = min($chunk_bytes, $send_len - $idx);
                    $written  = fwrite($out, substr($data, $idx, $to_write));
                    if ($written === false) { break;
                    }
                    $idx += $to_write;
                    fflush($out);
                    sleep(1);
                }
            } else {
                fwrite($out, $data);
            }
        }

        fclose($out);

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        return true;
    }
}

/* End of file download_helper.php */
/* Location: ./system/helpers/download_helper.php */
