<?php

declare(strict_types=1);

namespace NksHub\NetteCloudflareR2\Tracy;

use NksHub\NetteCloudflareR2\Client\R2Client;
use Tracy\IBarPanel;

/**
 * Tracy debugger panel for R2 operations monitoring.
 */
class R2Panel implements IBarPanel
{
    private R2Client $client;

    public function __construct(R2Client $client)
    {
        $this->client = $client;
    }

    public function getTab(): string
    {
        $stats = $this->client->getStats();
        $ops = $stats['operations'];

        return '<span title="Cloudflare R2">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align: -3px;">'
            . '<path fill="#F38020" d="M16.5 12c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5.67 1.5 1.5 1.5 1.5-.67 1.5-1.5z"/>'
            . '<path fill="#F38020" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>'
            . '<path fill="#F38020" d="M9 12c0-.83-.67-1.5-1.5-1.5S6 11.17 6 12s.67 1.5 1.5 1.5S9 12.83 9 12z"/>'
            . '</svg>'
            . '<span class="tracy-label">' . $ops . ' ops</span>'
            . '</span>';
    }

    public function getPanel(): string
    {
        $stats = $this->client->getStats();
        $config = $this->client->getConfiguration();

        $uploaded = $this->formatBytes($stats['bytesUploaded']);
        $downloaded = $this->formatBytes($stats['bytesDownloaded']);

        $html = '<h1>Cloudflare R2</h1>';
        $html .= '<div class="tracy-inner">';

        // Configuration
        $html .= '<table>';
        $html .= '<tr><th colspan="2">Configuration</th></tr>';
        $html .= '<tr><td>Bucket</td><td><code>' . htmlspecialchars($config->getDefaultBucket()) . '</code></td></tr>';
        $html .= '<tr><td>Public URL</td><td><code>' . htmlspecialchars($config->getPublicUrl() ?? 'N/A') . '</code></td></tr>';
        $html .= '<tr><td>Storage Class</td><td><code>' . htmlspecialchars($config->getDefaultStorageClass()) . '</code></td></tr>';
        $html .= '<tr><td>Jurisdiction</td><td><code>' . htmlspecialchars($config->getJurisdiction() ?? 'auto') . '</code></td></tr>';
        $html .= '</table>';

        // Statistics
        $html .= '<table>';
        $html .= '<tr><th colspan="2">Statistics</th></tr>';
        $html .= '<tr><td>Total Operations</td><td><b>' . $stats['operations'] . '</b></td></tr>';
        $html .= '<tr><td>Uploads</td><td>' . $stats['uploads'] . '</td></tr>';
        $html .= '<tr><td>Downloads</td><td>' . $stats['downloads'] . '</td></tr>';
        $html .= '<tr><td>Deletes</td><td>' . $stats['deletes'] . '</td></tr>';
        $html .= '<tr><td>Bytes Uploaded</td><td>' . $uploaded . '</td></tr>';
        $html .= '<tr><td>Bytes Downloaded</td><td>' . $downloaded . '</td></tr>';
        $html .= '<tr><td>Errors</td><td style="color: ' . ($stats['errors'] > 0 ? 'red' : 'green') . ';">' . $stats['errors'] . '</td></tr>';
        $html .= '</table>';

        $html .= '</div>';

        return $html;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
