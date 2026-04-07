<?php

class MayoristaBrandedPdf extends FPDF
{
    protected $footerBrandLogos = array();
    protected $footerWhatsappIcon = null;
    protected $footerInstagramIcon = null;
    protected $footerWhatsappText = '';
    protected $footerInstagramText = '';
    protected $footerNoteLines = array();
    protected $footerHeight = 26;
    protected $footerTotalPages = 0;

    public function setBrandFooter(
        array $brandLogos,
        $whatsappIcon,
        $whatsappText,
        $instagramIcon,
        $instagramText,
        array $noteLines = array()
    ) {
        $this->footerBrandLogos = array_values(array_filter($brandLogos, function ($path) {
            return is_string($path) && $path !== '' && file_exists($path);
        }));
        $this->footerWhatsappIcon = is_string($whatsappIcon) && file_exists($whatsappIcon) ? $whatsappIcon : null;
        $this->footerInstagramIcon = is_string($instagramIcon) && file_exists($instagramIcon) ? $instagramIcon : null;
        $this->footerWhatsappText = (string) $whatsappText;
        $this->footerInstagramText = (string) $instagramText;
        $this->footerNoteLines = array_values(array_filter($noteLines, function ($line) {
            return is_string($line) && trim($line) !== '';
        }));
        $this->footerHeight = !empty($this->footerNoteLines) ? 38 : 24;
    }

    public function getFooterHeight()
    {
        return $this->footerHeight;
    }

    public function setFooterPagination($totalPages)
    {
        $this->footerTotalPages = max(0, (int) $totalPages);
    }

    public function Footer()
    {
        if (
            empty($this->footerBrandLogos)
            && $this->footerWhatsappText === ''
            && $this->footerInstagramText === ''
            && empty($this->footerNoteLines)
            && $this->footerTotalPages <= 1
        ) {
            return;
        }

        $left = $this->lMargin;
        $right = $this->w - $this->rMargin;
        $topY = $this->h - $this->footerHeight;
        $currentY = $topY + 2;

        $this->SetDrawColor(222, 226, 232);
        $this->SetLineWidth(0.2);
        $this->Line($left, $topY, $right, $topY);

        if ($this->footerTotalPages > 1) {
            $this->SetFont('Arial', 'B', 8.4);
            $this->SetTextColor(110, 118, 128);
            $this->SetXY($left, $topY + 1.5);
            $this->Cell($right - $left, 4, utf8_decode('Pagina ' . $this->PageNo() . '/' . $this->footerTotalPages), 0, 1, 'R');
            $currentY += 4.2;
        }

        if (!empty($this->footerNoteLines)) {
            foreach ($this->footerNoteLines as $line) {
                $isStrong = strpos($line, '***') !== false;
                $this->SetFont('Arial', $isStrong ? 'B' : 'I', $isStrong ? 7.1 : 6.8);
                $this->SetTextColor($isStrong ? 70 : 100, $isStrong ? 70 : 116, $isStrong ? 70 : 139);
                $this->SetXY($left, $currentY);
                $this->Cell($right - $left, 3.2, utf8_decode($line), 0, 1, 'C');
                $currentY += 3.2;
            }
            $currentY += 0.8;
        }

        $this->SetTextColor(55, 65, 81);
        $slots = array();
        foreach ($this->footerBrandLogos as $logoPath) {
            $slots[] = array('type' => 'image', 'value' => $logoPath);
        }
        if ($this->footerWhatsappText !== '') {
            $slots[] = array('type' => 'text', 'value' => 'Tel: ' . $this->footerWhatsappText);
        }
        if ($this->footerInstagramText !== '') {
            $slots[] = array('type' => 'text', 'value' => 'Ig: ' . $this->footerInstagramText);
        }

        if (empty($slots)) {
            return;
        }

        $contentWidth = $right - $left;
        $slotWidth = $contentWidth / count($slots);
        $rowY = $currentY;
        $logoWidths = array(20, 22, 25);

        foreach ($slots as $index => $slot) {
            $slotLeft = $left + ($index * $slotWidth);

            if ($slot['type'] === 'image') {
                $logoWidth = isset($logoWidths[$index]) ? $logoWidths[$index] : 22;
                $imageX = $slotLeft + (($slotWidth - $logoWidth) / 2);
                $this->Image($slot['value'], $imageX, $rowY - 0.4, $logoWidth, 0, 'PNG');
                continue;
            }

            $this->SetFont('Arial', 'B', 8.8);
            $this->SetXY($slotLeft, $rowY + 3);
            $this->Cell($slotWidth, 4.2, utf8_decode($slot['value']), 0, 0, 'C');
        }
    }
}

function mayorista_pdf_footer_assets()
{
    return array(
        'brand_logos' => array(
            realpath(__DIR__ . '/../../assets/img/footer-roxbury.png'),
            realpath(__DIR__ . '/../../assets/img/footer-chiara.png'),
            realpath(__DIR__ . '/../../assets/img/footer-speedway.png'),
        ),
        'whatsapp_icon' => null,
        'instagram_icon' => null,
        'whatsapp_text' => '1176283425',
        'instagram_text' => '@argenoptik_',
    );
}
