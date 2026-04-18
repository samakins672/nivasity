<?php

require_once __DIR__ . '/receipt_pdf.php';

if (!class_exists('NivasityManualExportPdfComposer')) {
  class NivasityManualExportPdfComposer
  {
    private $pageWidth = 595.28;
    private $pageHeight = 841.89;
    private $contentTop = 56.0;
    private $contentBottom = 780.0;
    private $cursorY = 56.0;
    private $pages = [];
    private $content = '';
    private $watermarkImage = [];

    public function __construct(array $watermarkImage)
    {
      $this->watermarkImage = $watermarkImage;
      $this->startPage();
    }

    public function output(array $payload)
    {
      $title = strtoupper(trim((string) ($payload['heading'] ?? 'MATERIAL EXPORT LIST')));
      $metaLines = $payload['meta_lines'] ?? [];
      $headers = $payload['headers'] ?? [];
      $rows = $payload['rows'] ?? [];

      $this->drawWatermark();
      $this->textCentered($this->pageWidth / 2, 64, $title, 'F2', 16, [0.130, 0.130, 0.130]);
      $this->cursorY = 92;

      foreach ($metaLines as $line) {
        $this->ensureSpace(16);
        $this->text(50, $this->cursorY, (string) $line, 'F1', 10.5, [0.170, 0.170, 0.170]);
        $this->cursorY += 16;
      }

      $this->cursorY += 10;
      $this->drawTableHeader($headers);
      foreach ($rows as $row) {
        $this->drawTableRow($headers, $row);
      }

      $this->finishPage();
      return $this->buildPdf();
    }

    private function startPage()
    {
      $this->content = '';
      $this->cursorY = $this->contentTop;
      $this->drawWatermark();
    }

    private function finishPage()
    {
      $this->pages[] = $this->content;
      $this->content = '';
    }

    private function ensureSpace($height)
    {
      if (($this->cursorY + $height) <= $this->contentBottom) {
        return false;
      }

      $this->finishPage();
      $this->startPage();
      $this->cursorY = 56;
      return true;
    }

    private function drawWatermark()
    {
      $watermarkWidth = $this->pageWidth * 0.80;
      $aspectRatio = $this->watermarkImage['height'] / max(1, $this->watermarkImage['width']);
      $watermarkHeight = $watermarkWidth * $aspectRatio;
      $this->drawRotatedCenteredImage('Im1', $this->pageWidth / 2, $this->pageHeight / 2, $watermarkWidth, $watermarkHeight, -45.0, 0.08);
    }

    private function drawTableHeader(array $headers)
    {
      $this->ensureSpace(26);
      $this->line(50, $this->cursorY + 22, 545, $this->cursorY + 22, [0.870, 0.870, 0.870], 1.0);
      foreach ($headers as $header) {
        $align = $header['align'] ?? 'left';
        $fontSize = 9.0;
        if ($align === 'right') {
          $this->textRight((float) $header['x'], $this->cursorY + 14, (string) $header['label'], 'F2', $fontSize, [0.260, 0.260, 0.260]);
        } else {
          $this->text((float) $header['x'], $this->cursorY + 14, (string) $header['label'], 'F2', $fontSize, [0.260, 0.260, 0.260]);
        }
      }
      $this->cursorY += 26;
    }

    private function drawTableRow(array $headers, array $row)
    {
      $lineCounts = [];
      foreach ($headers as $header) {
        $key = (string) $header['key'];
        $lineCounts[] = count($this->wrapText((string) ($row[$key] ?? ''), (float) $header['width'], 9.5));
      }
      $rowHeight = (max($lineCounts ?: [1]) * 12) + 8;
      if ($this->ensureSpace($rowHeight + 6)) {
        $this->drawTableHeader($headers);
      }
      $this->line(50, $this->cursorY + $rowHeight, 545, $this->cursorY + $rowHeight, [0.920, 0.920, 0.920], 0.8);

      foreach ($headers as $header) {
        $key = (string) $header['key'];
        $textLines = $this->wrapText((string) ($row[$key] ?? ''), (float) $header['width'], 9.5);
        $lineY = $this->cursorY + 12;
        foreach ($textLines as $line) {
          if (($header['align'] ?? 'left') === 'right') {
            $this->textRight((float) $header['x'], $lineY, $line, 'F1', 9.5, [0.170, 0.170, 0.170]);
          } else {
            $this->text((float) $header['x'], $lineY, $line, 'F1', 9.5, [0.170, 0.170, 0.170]);
          }
          $lineY += 12;
        }
      }

      $this->cursorY += $rowHeight;
    }

    private function wrapText($text, $maxWidth, $fontSize)
    {
      $normalized = preg_replace('/\s+/u', ' ', trim($text));
      if ($normalized === null || $normalized === '') {
        return [''];
      }

      $words = preg_split('/\s+/u', $normalized) ?: [$normalized];
      $lines = [];
      $line = '';

      foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if ($this->estimateTextWidth($candidate, $fontSize) <= $maxWidth) {
          $line = $candidate;
          continue;
        }

        if ($line !== '') {
          $lines[] = $line;
          $line = $word;
        } else {
          $lines[] = $word;
          $line = '';
        }
      }

      if ($line !== '') {
        $lines[] = $line;
      }

      return $lines === [] ? [''] : $lines;
    }

    private function estimateTextWidth($text, $fontSize)
    {
      return strlen(receipt_pdf_escape_text($text)) * ($fontSize * 0.50);
    }

    private function text($x, $yTop, $text, $font, $size, array $color)
    {
      $bottomY = $this->pageHeight - $yTop - ($size * 0.82);
      $escaped = receipt_pdf_escape_text($text);
      $this->content .= sprintf("q %.3F %.3F %.3F rg BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET Q\n", $color[0], $color[1], $color[2], $font, $size, $x, $bottomY, $escaped);
    }

    private function textCentered($centerX, $yTop, $text, $font, $size, array $color)
    {
      $width = $this->estimateTextWidth($text, $size);
      $this->text($centerX - ($width / 2), $yTop, $text, $font, $size, $color);
    }

    private function textRight($rightX, $yTop, $text, $font, $size, array $color)
    {
      $width = $this->estimateTextWidth($text, $size);
      $this->text(max(50.0, $rightX - $width), $yTop, $text, $font, $size, $color);
    }

    private function line($x1, $y1Top, $x2, $y2Top, array $color, $width)
    {
      $y1 = $this->pageHeight - $y1Top;
      $y2 = $this->pageHeight - $y2Top;
      $this->content .= sprintf("q %.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S Q\n", $color[0], $color[1], $color[2], $width, $x1, $y1, $x2, $y2);
    }

    private function drawRotatedCenteredImage($imageName, $centerX, $centerYTop, $width, $height, $degrees, $opacity)
    {
      $angle = deg2rad($degrees);
      $cos = cos($angle);
      $sin = sin($angle);
      $centerYBottom = $this->pageHeight - $centerYTop;
      $this->content .= sprintf(
        "q /GS1 gs 1 0 0 1 %.2F %.2F cm %.5F %.5F %.5F %.5F 0 0 cm %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
        $centerX,
        $centerYBottom,
        $cos,
        $sin,
        -$sin,
        $cos,
        $width,
        $height,
        -$width / 2,
        -$height / 2,
        $imageName
      );
    }

    private function buildPdf()
    {
      $objects = [];
      $addObject = static function ($body) use (&$objects) {
        $objects[] = $body;
        return count($objects);
      };

      $fontRegularId = $addObject("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");
      $fontBoldId = $addObject("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>");
      $gStateId = $addObject("<< /Type /ExtGState /ca 0.080 /CA 0.080 >>");

      $watermarkImageData = $this->watermarkImage['data'];
      $watermarkImageId = $addObject(
        "<< /Type /XObject /Subtype /Image /Width {$this->watermarkImage['width']} /Height {$this->watermarkImage['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length "
        . strlen($watermarkImageData)
        . " >>\nstream\n"
        . $watermarkImageData
        . "\nendstream"
      );

      $pagesNodeId = $addObject('');
      $pageIds = [];

      foreach ($this->pages as $pageContent) {
        $contentStream = gzcompress($pageContent, 9);
        $contentId = $addObject("<< /Length " . strlen($contentStream) . " /Filter /FlateDecode >>\nstream\n" . $contentStream . "\nendstream");
        $pageId = $addObject(
          "<< /Type /Page /Parent {$pagesNodeId} 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] /Resources << /Font << /F1 {$fontRegularId} 0 R /F2 {$fontBoldId} 0 R >> /XObject << /Im1 {$watermarkImageId} 0 R >> /ExtGState << /GS1 {$gStateId} 0 R >> >> /Contents {$contentId} 0 R >>"
        );
        $pageIds[] = $pageId;
      }

      $kids = implode(' ', array_map(static function ($pageId) {
        return $pageId . ' 0 R';
      }, $pageIds));
      $objects[$pagesNodeId - 1] = "<< /Type /Pages /Count " . count($pageIds) . " /Kids [ {$kids} ] >>";
      $catalogId = $addObject("<< /Type /Catalog /Pages {$pagesNodeId} 0 R >>");

      $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
      $offsets = [0];
      foreach ($objects as $index => $body) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $body . "\nendobj\n";
      }

      $xrefOffset = strlen($pdf);
      $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
      $pdf .= "0000000000 65535 f \n";
      for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
      }

      $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root {$catalogId} 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
      return $pdf;
    }
  }
}

if (!function_exists('manual_export_pdf_render')) {
  function manual_export_pdf_render(array $payload, $watermarkPath = null)
  {
    $resolvedWatermarkPath = $watermarkPath ?: dirname(__DIR__) . '/assets/images/nivasity-main.png';
    $watermarkImage = receipt_pdf_parse_png($resolvedWatermarkPath);
    $composer = new NivasityManualExportPdfComposer($watermarkImage);
    return $composer->output($payload);
  }
}