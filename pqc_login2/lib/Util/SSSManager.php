<?php
namespace OCA\PQCLogin\Util;

class SSSManager {
    /**
     * Shamir's Secret Sharing over GF(2^8)
     */
    private static $exp = [];
    private static $log = [];
    private static $initialized = false;

    private static function init() {
        if (self::$initialized) return;
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11d; // AES polynomial
        }
        for ($i = 255; $i < 510; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
        self::$initialized = true;
    }

    private static function mul($a, $b) {
        if ($a == 0 || $b == 0) return 0;
        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    private static function div($a, $b) {
        if ($b == 0) throw new \Exception("Division by zero in GF(2^8)");
        if ($a == 0) return 0;
        return self::$exp[self::$log[$a] - self::$log[$b] + 255];
    }

    /**
     * Split a secret into n shares with threshold t
     */
    public function split(string $secretBin, int $n = 3, int $t = 2): array {
        self::init();
        $len = strlen($secretBin);
        $shares = array_fill(0, $n, '');
        
        for ($i = 0; $i < $n; $i++) {
            $shares[$i] = chr($i + 1); // First byte is the x-coordinate (1-indexed)
        }

        for ($s = 0; $s < $len; $s++) {
            $secretByte = ord($secretBin[$s]);
            $poly = [$secretByte];
            for ($j = 1; $j < $t; $j++) {
                $poly[] = random_int(0, 255);
            }

            for ($i = 1; $i <= $n; $i++) {
                $val = 0;
                $x_pow = 1;
                foreach ($poly as $coeff) {
                    $val ^= self::mul($coeff, $x_pow);
                    $x_pow = self::mul($x_pow, $i);
                }
                $shares[$i - 1] .= chr($val);
            }
        }

        return array_map('bin2hex', $shares);
    }

    /**
     * Reconstruct secret from shares
     */
    public function reconstruct(array $sharesHex): string {
        self::init();
        $shares = array_map('hex2bin', $sharesHex);
        $t = count($shares);
        if ($t < 2) throw new \Exception("At least 2 shares required");

        $len = strlen($shares[0]) - 1;
        $secret = '';

        for ($s = 0; $s < $len; $s++) {
            $points = [];
            foreach ($shares as $share) {
                $points[] = [ord($share[0]), ord($share[$s + 1])];
            }

            $val = 0;
            for ($i = 0; $i < $t; $i++) {
                $li = 1;
                for ($j = 0; $j < $t; $j++) {
                    if ($i === $j) continue;
                    $num = $points[$j][0];
                    $den = $points[$i][0] ^ $points[$j][0];
                    $li = self::mul($li, self::div($num, $den));
                }
                $val ^= self::mul($points[$i][1], $li);
            }
            $secret .= chr($val);
        }

        return $secret;
    }
}
