<?php
/**
 * Builds a professional RTL patient-conversation report.
 *
 * Produces a real PDF when a PDF engine (mPDF or Dompdf) is installed on the
 * site; otherwise it writes a print-optimized HTML document (the browser
 * renders Persian/RTL flawlessly via "Print → Save as PDF"). Files are stored
 * under uploads/swc-pdf/YYYY/MM/ with an unguessable name.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Export;

if (! defined('ABSPATH')) {
    exit;
}

class PdfGenerator
{
    private \SignTeb\WebChat\Core\Settings $settings;
    private \SignTeb\WebChat\Export\LeadPayload $payloads;

    public function __construct(?\SignTeb\WebChat\Core\Settings $settings = null, ?\SignTeb\WebChat\Export\LeadPayload $payloads = null)
    {
        $this->settings = $settings ?? new \SignTeb\WebChat\Core\Settings();
        $this->payloads = $payloads ?? new \SignTeb\WebChat\Export\LeadPayload();
    }

    /**
     * Generate (or regenerate) the report for a lead.
     *
     * @return array{ok:bool,path?:string,url?:string,type?:string,error?:string}
     */
    public function generate(int $lead_id): array
    {
        $payload = $this->payloads->build($lead_id);
        if ($payload === null) {
            return ['ok' => false, 'error' => 'lead_not_found'];
        }

        $dir = $this->target_dir();
        if ($dir === null) {
            return ['ok' => false, 'error' => 'upload_dir_unwritable'];
        }

        $html = $this->render_html($payload);
        $base = 'lead-' . $lead_id . '-' . $this->token($lead_id);

        if (class_exists('\\Mpdf\\Mpdf')) {
            $result = $this->render_with_mpdf($html, $dir['path'] . '/' . $base . '.pdf');
            if ($result) {
                return ['ok' => true, 'path' => $dir['path'] . '/' . $base . '.pdf', 'url' => $dir['url'] . '/' . $base . '.pdf', 'type' => 'pdf'];
            }
        }
        if (class_exists('\\Dompdf\\Dompdf')) {
            $result = $this->render_with_dompdf($html, $dir['path'] . '/' . $base . '.pdf');
            if ($result) {
                return ['ok' => true, 'path' => $dir['path'] . '/' . $base . '.pdf', 'url' => $dir['url'] . '/' . $base . '.pdf', 'type' => 'pdf'];
            }
        }

        // Fallback: store a print-ready HTML document.
        $path = $dir['path'] . '/' . $base . '.html';
        if (file_put_contents($path, $html) === false) {
            return ['ok' => false, 'error' => 'write_failed'];
        }
        return ['ok' => true, 'path' => $path, 'url' => $dir['url'] . '/' . $base . '.html', 'type' => 'html'];
    }

    /**
     * Deterministic, unguessable file token so the stored URL acts as a
     * capability URL for external consumers (e.g. webhook payloads).
     */
    private function token(int $lead_id): string
    {
        return substr(hash_hmac('sha256', 'pdf|' . $lead_id, wp_salt('secure_auth')), 0, 16);
    }

    /**
     * @return array{path:string,url:string}|null
     */
    private function target_dir(): ?array
    {
        $uploads = wp_upload_dir();
        if (! empty($uploads['error'])) {
            return null;
        }
        $sub  = '/swc-pdf/' . gmdate('Y') . '/' . gmdate('m');
        $path = $uploads['basedir'] . $sub;
        if (! wp_mkdir_p($path)) {
            return null;
        }
        // Prevent directory listing.
        if (! file_exists($uploads['basedir'] . '/swc-pdf/index.php')) {
            file_put_contents($uploads['basedir'] . '/swc-pdf/index.php', "<?php\n// Silence is golden.\n");
        }
        return ['path' => $path, 'url' => $uploads['baseurl'] . $sub];
    }

    private function render_with_mpdf(string $html, string $path): bool
    {
        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode'           => 'utf-8',
                'format'         => 'A4',
                'directionality' => 'rtl',
                'autoScriptToLang' => true,
                'autoLangToFont'   => true,
            ]);
            $mpdf->WriteHTML($html);
            $mpdf->Output($path, \Mpdf\Output\Destination::FILE);
            return file_exists($path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function render_with_dompdf(string $html, string $path): bool
    {
        try {
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4');
            $dompdf->render();
            return file_put_contents($path, $dompdf->output()) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The VIP report markup (self-contained, inline CSS, print-friendly).
     */
    public function render_html(array $payload): string
    {
        $clinic     = (string) $this->settings->get('clinic_name', get_bloginfo('name'));
        $accent     = (string) $this->settings->get('accent_color', '#c8a04e');
        $navy       = (string) $this->settings->get('widget_color', '#0f1f3d');
        $name       = $payload['patient_name'] !== '' ? $payload['patient_name'] : '—';
        $phone      = $payload['phone'] !== '' ? $payload['phone'] : '—';
        $req        = $payload['request_type'] !== '' ? $payload['request_type'] : '—';
        $jalali     = $this->jalali_datetime($payload['created_at']);
        $exported   = $this->jalali_datetime(current_time('mysql'));
        $score_map  = ['hot' => 'داغ', 'warm' => 'متوسط', 'cold' => 'سرد'];
        $score      = $score_map[$payload['lead_score']] ?? '—';

        $rows = '';
        foreach ($payload['messages'] as $m) {
            $is_user = $m['role'] === 'user';
            $who     = $is_user ? 'بیمار' : 'دستیار SignTeb Chat';
            $side    = $is_user ? 'swc-p' : 'swc-a';
            $rows   .= '<div class="msg ' . $side . '">'
                . '<div class="meta"><span class="who">' . esc_html($who) . '</span>'
                . '<span class="time">' . esc_html($this->jalali_datetime($m['timestamp'])) . '</span></div>'
                . '<div class="text">' . nl2br(esc_html($m['message'])) . '</div></div>';
        }

        $summary_html = $payload['summary'] !== ''
            ? '<div class="summary"><h3>خلاصه هوشمند</h3><pre>' . esc_html($payload['summary']) . '</pre></div>'
            : '';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html('گزارش مکالمه بیمار - ' . $name); ?></title>
<style>
    * { box-sizing: border-box; }
    body { font-family: Vazirmatn, Tahoma, sans-serif; color: #1a2540; margin: 0; padding: 32px; background: #fff; direction: rtl; }
    .report { max-width: 800px; margin: 0 auto; }
    .head { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid <?php echo esc_attr($accent); ?>; padding-bottom: 16px; }
    .brand { font-size: 22px; font-weight: 800; color: <?php echo esc_attr($navy); ?>; }
    .brand small { display: block; font-size: 12px; font-weight: 400; color: #6b7a8e; letter-spacing: 1px; }
    .doc-title { font-size: 15px; color: <?php echo esc_attr($accent); ?>; font-weight: 700; }
    .infobox { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; background: #f6f8fc; border: 1px solid #e2e6ef; border-radius: 12px; padding: 18px 22px; margin: 22px 0; }
    .infobox div { font-size: 13.5px; }
    .infobox b { color: <?php echo esc_attr($navy); ?>; }
    h2.section { font-size: 15px; color: <?php echo esc_attr($navy); ?>; border-right: 4px solid <?php echo esc_attr($accent); ?>; padding-right: 10px; margin: 26px 0 14px; }
    .summary { background: #fffaf0; border: 1px solid <?php echo esc_attr($accent); ?>; border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; }
    .summary h3 { margin: 0 0 8px; font-size: 14px; color: <?php echo esc_attr($navy); ?>; }
    .summary pre { margin: 0; white-space: pre-wrap; font-family: inherit; font-size: 13px; line-height: 1.9; }
    .msg { margin: 0 0 12px; padding: 12px 16px; border-radius: 12px; max-width: 88%; }
    .swc-p { background: #eef2fb; border: 1px solid #dbe2f2; margin-inline-start: auto; }
    .swc-a { background: #fff; border: 1px solid #e6e9f0; }
    .meta { display: flex; justify-content: space-between; font-size: 11px; color: #7a869e; margin-bottom: 6px; }
    .who { font-weight: 700; color: <?php echo esc_attr($navy); ?>; }
    .text { font-size: 13.5px; line-height: 1.9; }
    .foot { margin-top: 30px; border-top: 1px solid #e2e6ef; padding-top: 14px; font-size: 11px; color: #7a869e; display: flex; justify-content: space-between; }
    @media print { body { padding: 0; } .report { max-width: 100%; } }
</style>
</head>
<body>
<div class="report">
    <div class="head">
        <div class="brand"><?php echo esc_html($clinic); ?><small>SIGNTEB</small></div>
        <div class="doc-title">گزارش مکالمه بیمار</div>
    </div>

    <div class="infobox">
        <div><b>نام بیمار:</b> <?php echo esc_html($name); ?></div>
        <div><b>شماره تماس:</b> <?php echo esc_html($phone); ?></div>
        <div><b>تاریخ:</b> <?php echo esc_html($jalali); ?></div>
        <div><b>نوع درخواست:</b> <?php echo esc_html($req); ?></div>
        <div><b>شناسه لید:</b> #<?php echo esc_html((string) $payload['lead_id']); ?></div>
        <div><b>وضعیت لید:</b> <?php echo esc_html($score); ?></div>
    </div>

    <?php echo wp_kses_post($summary_html); ?>

    <h2 class="section">متن گفتگو</h2>
    <?php echo wp_kses_post($rows); ?>

    <div class="foot">
        <span>SignTeb Chat · <?php echo esc_html($exported); ?></span>
        <span>Lead #<?php echo esc_html((string) $payload['lead_id']); ?></span>
    </div>
</div>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Convert a "Y-m-d H:i:s" Gregorian datetime to a Jalali string.
     */
    private function jalali_datetime(string $mysql): string
    {
        $ts = strtotime($mysql);
        if ($ts === false) {
            return $mysql;
        }
        [$gy, $gm, $gd] = array_map('intval', explode('-', gmdate('Y-m-d', $ts)));
        [$jy, $jm, $jd] = self::gregorian_to_jalali($gy, $gm, $gd);
        return sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, gmdate('H:i', $ts));
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private static function gregorian_to_jalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2   = $gy - 1600;
        $gm2   = $gm - 1;
        $gd2   = $gd - 1;
        $g_day_no = 365 * $gy2 + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400);
        $g_day_no += $g_d_m[$gm2] + $gd2;
        if ($gm2 > 1 && (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0)) {
            $g_day_no++;
        }
        $j_day_no = $g_day_no - 79;
        $j_np     = intdiv($j_day_no, 12053);
        $j_day_no %= 12053;
        $jy = 979 + 33 * $j_np + 4 * intdiv($j_day_no, 1461);
        $j_day_no %= 1461;
        if ($j_day_no >= 366) {
            $jy += intdiv($j_day_no - 366, 365) + 1;
            $j_day_no = ($j_day_no - 366) % 365;
        }
        $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        $jm = 0;
        while ($jm < 12 && $j_day_no >= $j_days_in_month[$jm]) {
            $j_day_no -= $j_days_in_month[$jm];
            $jm++;
        }
        return [$jy, $jm + 1, $j_day_no + 1];
    }
}
