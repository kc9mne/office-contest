<?php
declare(strict_types=1);

/**
 * Minimal QR code generator: byte mode, error correction level M, versions 1–10
 * (up to 213 bytes, plenty for a link). Returns an SVG string.
 * Follows ISO/IEC 18004; structure based on Project Nayuki's reference design.
 */
function qr_svg(string $text, int $quietZone = 4): string
{
    $m = qr_matrix($text);
    $size = count($m);
    $full = $size + 2 * $quietZone;
    $path = '';
    foreach ($m as $y => $row) {
        foreach ($row as $x => $dark) {
            if ($dark) {
                $path .= 'M' . ($x + $quietZone) . ',' . ($y + $quietZone) . 'h1v1h-1z';
            }
        }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $full . ' ' . $full . '" shape-rendering="crispEdges" role="img" aria-label="QR code">'
        . '<rect width="100%" height="100%" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
}

/** @return bool[][] rows of modules, true = dark */
function qr_matrix(string $text): array
{
    // [total codewords, ec codewords per block, [[block count, data codewords per block], ...]] for level M
    $table = [
        1 => [26, 10, [[1, 16]]],
        2 => [44, 16, [[1, 28]]],
        3 => [70, 26, [[1, 44]]],
        4 => [100, 18, [[2, 32]]],
        5 => [134, 24, [[2, 43]]],
        6 => [172, 16, [[4, 27]]],
        7 => [196, 18, [[4, 31]]],
        8 => [242, 22, [[2, 38], [2, 39]]],
        9 => [292, 22, [[3, 36], [2, 37]]],
        10 => [346, 26, [[4, 43], [1, 44]]],
    ];
    $align = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
        7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];

    $bytes = array_values(unpack('C*', $text) ?: []);
    $len = count($bytes);

    $version = null;
    foreach ($table as $v => [$total, $ec, $groups]) {
        $dataCap = 0;
        foreach ($groups as [$n, $k]) {
            $dataCap += $n * $k;
        }
        $countBits = $v <= 9 ? 8 : 16;
        if (4 + $countBits + 8 * $len <= $dataCap * 8) {
            $version = $v;
            break;
        }
    }
    if ($version === null) {
        throw new InvalidArgumentException('Text too long for a QR code.');
    }
    [$total, $ecLen, $groups] = $table[$version];
    $dataCap = 0;
    foreach ($groups as [$n, $k]) {
        $dataCap += $n * $k;
    }

    // --- bit stream ---
    $bits = [];
    $push = function (int $value, int $count) use (&$bits): void {
        for ($i = $count - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    };
    $push(0b0100, 4);
    $push($len, $version <= 9 ? 8 : 16);
    foreach ($bytes as $b) {
        $push($b, 8);
    }
    $capBits = $dataCap * 8;
    $push(0, min(4, $capBits - count($bits)));
    $push(0, (8 - count($bits) % 8) % 8);
    for ($pad = 0xEC; count($bits) < $capBits; $pad ^= 0xEC ^ 0x11) {
        $push($pad, 8);
    }
    $data = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $byte = 0;
        for ($j = 0; $j < 8; $j++) {
            $byte = ($byte << 1) | $bits[$i + $j];
        }
        $data[] = $byte;
    }

    // --- split into blocks, add Reed-Solomon error correction, interleave ---
    $gen = qr_rs_generator($ecLen);
    $blocks = [];
    $offset = 0;
    foreach ($groups as [$n, $k]) {
        for ($b = 0; $b < $n; $b++) {
            $chunk = array_slice($data, $offset, $k);
            $offset += $k;
            $blocks[] = [$chunk, qr_rs_remainder($chunk, $gen)];
        }
    }
    $codewords = [];
    $maxData = max(array_map(fn($blk) => count($blk[0]), $blocks));
    for ($i = 0; $i < $maxData; $i++) {
        foreach ($blocks as [$d]) {
            if ($i < count($d)) {
                $codewords[] = $d[$i];
            }
        }
    }
    for ($i = 0; $i < $ecLen; $i++) {
        foreach ($blocks as [, $e]) {
            $codewords[] = $e[$i];
        }
    }

    // --- function patterns ---
    $size = 17 + 4 * $version;
    $mod = array_fill(0, $size, array_fill(0, $size, false));
    $fn = array_fill(0, $size, array_fill(0, $size, false));
    $set = function (int $x, int $y, bool $dark) use (&$mod, &$fn): void {
        $mod[$y][$x] = $dark;
        $fn[$y][$x] = true;
    };

    for ($i = 0; $i < $size; $i++) {
        $set(6, $i, $i % 2 === 0);
        $set($i, 6, $i % 2 === 0);
    }
    foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) {
                    $dist = max(abs($dx), abs($dy));
                    $set($x, $y, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }
    $pos = $align[$version];
    $np = count($pos);
    for ($i = 0; $i < $np; $i++) {
        for ($j = 0; $j < $np; $j++) {
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $np - 1) || ($i === $np - 1 && $j === 0)) {
                continue;
            }
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $set($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                }
            }
        }
    }
    qr_draw_format($set, $size, 0); // reserve format areas (real bits drawn after masking)
    if ($version >= 7) {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $vbits = ($version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $bit = (($vbits >> $i) & 1) === 1;
            $a = $size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $set($a, $b, $bit);
            $set($b, $a, $bit);
        }
    }

    // --- data placement (zigzag) ---
    $allBits = [];
    foreach ($codewords as $cw) {
        for ($i = 7; $i >= 0; $i--) {
            $allBits[] = ($cw >> $i) & 1;
        }
    }
    $i = 0;
    $nBits = count($allBits);
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) {
            $right = 5;
        }
        for ($vert = 0; $vert < $size; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $right - $j;
                $upward = (($right + 1) & 2) === 0;
                $y = $upward ? $size - 1 - $vert : $vert;
                if (!$fn[$y][$x] && $i < $nBits) {
                    $mod[$y][$x] = $allBits[$i] === 1;
                    $i++;
                }
            }
        }
    }

    // --- choose the mask with the lowest penalty ---
    $best = null;
    $bestScore = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $candidate = qr_apply_mask($mod, $fn, $mask);
        $setC = function (int $x, int $y, bool $dark) use (&$candidate): void {
            $candidate[$y][$x] = $dark;
        };
        qr_draw_format($setC, $size, $mask);
        $score = qr_penalty($candidate);
        if ($score < $bestScore) {
            $bestScore = $score;
            $best = $candidate;
        }
    }
    return $best;
}

function qr_draw_format(callable $set, int $size, int $mask): void
{
    $data = (0b00 << 3) | $mask; // level M = 00
    $rem = $data;
    for ($i = 0; $i < 10; $i++) {
        $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
    }
    $bits = (($data << 10) | $rem) ^ 0x5412;
    $bit = fn(int $i) => (($bits >> $i) & 1) === 1;

    for ($i = 0; $i <= 5; $i++) {
        $set(8, $i, $bit($i));
    }
    $set(8, 7, $bit(6));
    $set(8, 8, $bit(7));
    $set(7, 8, $bit(8));
    for ($i = 9; $i < 15; $i++) {
        $set(14 - $i, 8, $bit($i));
    }
    for ($i = 0; $i < 8; $i++) {
        $set($size - 1 - $i, 8, $bit($i));
    }
    for ($i = 8; $i < 15; $i++) {
        $set(8, $size - 15 + $i, $bit($i));
    }
    $set(8, $size - 8, true); // dark module
}

function qr_apply_mask(array $mod, array $fn, int $mask): array
{
    $size = count($mod);
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if ($fn[$y][$x]) {
                continue;
            }
            $invert = match ($mask) {
                0 => ($x + $y) % 2 === 0,
                1 => $y % 2 === 0,
                2 => $x % 3 === 0,
                3 => ($x + $y) % 3 === 0,
                4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                7 => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
            };
            if ($invert) {
                $mod[$y][$x] = !$mod[$y][$x];
            }
        }
    }
    return $mod;
}

function qr_penalty(array $m): int
{
    $size = count($m);
    $score = 0;
    $dark = 0;
    $lines = [];
    for ($i = 0; $i < $size; $i++) {
        $row = [];
        $col = [];
        for ($j = 0; $j < $size; $j++) {
            $row[] = $m[$i][$j] ? 1 : 0;
            $col[] = $m[$j][$i] ? 1 : 0;
            $dark += $m[$i][$j] ? 1 : 0;
        }
        $lines[] = $row;
        $lines[] = $col;
    }
    foreach ($lines as $line) {
        $run = 1;
        for ($j = 1; $j <= $size; $j++) {
            if ($j < $size && $line[$j] === $line[$j - 1]) {
                $run++;
            } else {
                if ($run >= 5) {
                    $score += 3 + ($run - 5);
                }
                $run = 1;
            }
        }
        $s = implode('', $line);
        $score += 40 * (substr_count($s, '10111010000') + substr_count($s, '00001011101'));
    }
    for ($y = 0; $y < $size - 1; $y++) {
        for ($x = 0; $x < $size - 1; $x++) {
            $c = $m[$y][$x];
            if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                $score += 3;
            }
        }
    }
    $total = $size * $size;
    $k = (int) ceil(abs($dark * 20 - $total * 10) / $total) - 1;
    return $score + max(0, $k) * 10;
}

/** Reed-Solomon over GF(256) with polynomial 0x11D. */
function qr_gf_mul(int $a, int $b): int
{
    $r = 0;
    for ($i = 7; $i >= 0; $i--) {
        $r = ($r << 1) ^ (($r >> 7) * 0x11D);
        $r ^= (($b >> $i) & 1) * $a;
    }
    return $r & 0xFF;
}

function qr_rs_generator(int $degree): array
{
    $result = array_fill(0, $degree, 0);
    $result[$degree - 1] = 1;
    $root = 1;
    for ($i = 0; $i < $degree; $i++) {
        for ($j = 0; $j < $degree; $j++) {
            $result[$j] = qr_gf_mul($result[$j], $root);
            if ($j + 1 < $degree) {
                $result[$j] ^= $result[$j + 1];
            }
        }
        $root = qr_gf_mul($root, 0x02);
    }
    return $result;
}

function qr_rs_remainder(array $data, array $gen): array
{
    $result = array_fill(0, count($gen), 0);
    foreach ($data as $b) {
        $factor = $b ^ array_shift($result);
        $result[] = 0;
        foreach ($gen as $i => $coef) {
            $result[$i] ^= qr_gf_mul($coef, $factor);
        }
    }
    return $result;
}
