<?php
namespace OCA\PQCLogin\Util;

use OCP\IConfig;

class MRSManager {
    private IConfig $config;
    private SSSManager $sss;

    public function __construct(IConfig $config, SSSManager $sss) {
        $this->config = $config;
        $this->sss = $sss;
    }

    public function initializeForUser(string $userId): array {
        $mrs = random_bytes(32);
        $shares = $this->sss->split($mrs, 3, 2); // Share A=0, B=1, C=2

        $this->config->setUserValue($userId, 'pqc_login', 'pqc_share_a', $shares[0]);
        $this->config->setUserValue($userId, 'pqc_login', 'pqc_mrs_hash', hash('sha256', $mrs));
        $version = (int)$this->config->getUserValue($userId, 'pqc_login', 'pqc_mrs_version', '0');
        $this->config->setUserValue($userId, 'pqc_login', 'pqc_mrs_version', (string)($version + 1));

        return [
            'share_b' => $this->formatShareB($shares[1]),
            'share_c' => $shares[2],
            'version' => $version + 1
        ];
    }

    public function verifyAndBurn(string $userId, string $userShareHex): ?string {
        $shareA = $this->config->getUserValue($userId, 'pqc_login', 'pqc_share_a', '');
        $expectedHash = $this->config->getUserValue($userId, 'pqc_login', 'pqc_mrs_hash', '');

        if (!$shareA || !$expectedHash) return null;

        try {
            // Normalize share if it was B (mnemonic format)
            $cleanShare = str_replace('-', '', $userShareHex);
            if (strlen($cleanShare) !== strlen($shareA)) {
                 // Try if it's already hex
                 $cleanShare = $userShareHex;
            }

            $mrs = $this->sss->reconstruct([$shareA, $cleanShare]);
            if (hash('sha256', $mrs) === $expectedHash) {
                // BURN: Invalidate current Share A
                $this->config->setUserValue($userId, 'pqc_login', 'pqc_share_a', 'BURNED_' . time());
                return bin2hex($mrs);
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    private function formatShareB(string $hex): string {
        // Format hex as xxxx-xxxx-xxxx...
        $parts = str_split($hex, 4);
        return implode('-', $parts);
    }
}
