<?php

if (!function_exists('receipt_pdf_escape_text')) {
  function receipt_pdf_escape_text(string $text): string
  {
    $normalized = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $normalized = preg_replace('/\s+/u', ' ', trim($normalized));
    $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $normalized);
    if ($encoded === false) {
      $encoded = preg_replace('/[^\x20-\x7E]/', '', $normalized);
    }

    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $encoded);
  }
}

if (!function_exists('receipt_pdf_parse_png')) {
  function receipt_pdf_parse_png(string $filePath): array
  {
    if (!is_file($filePath)) {
      throw new RuntimeException('Receipt logo file not found.');
    }

    $data = file_get_contents($filePath);
    if ($data === false || substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
      throw new RuntimeException('Receipt logo must be a valid PNG file.');
    }

    $offset = 8;
    $width = 0;
    $height = 0;
    $bitDepth = 0;
    $colorType = 0;
    $idat = '';

    while ($offset < strlen($data)) {
      $length = unpack('N', substr($data, $offset, 4))[1];
      $type = substr($data, $offset + 4, 4);
      $chunk = substr($data, $offset + 8, $length);
      $offset += 12 + $length;

      if ($type === 'IHDR') {
        $meta = unpack('Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace', $chunk);
        $width = (int) $meta['width'];
        $height = (int) $meta['height'];
        $bitDepth = (int) $meta['bitDepth'];
        $colorType = (int) $meta['colorType'];
        if ($bitDepth !== 8 || !in_array($colorType, [2, 6], true) || (int) $meta['compression'] !== 0 || (int) $meta['filter'] !== 0 || (int) $meta['interlace'] !== 0) {
          throw new RuntimeException('Receipt logo PNG uses an unsupported format.');
        }
      } elseif ($type === 'IDAT') {
        $idat .= $chunk;
      } elseif ($type === 'IEND') {
        break;
      }
    }

    $decoded = function_exists('zlib_decode') ? zlib_decode($idat) : gzuncompress($idat);
    if ($decoded === false) {
      throw new RuntimeException('Unable to decode receipt logo PNG.');
    }

    $bytesPerPixel = ($colorType === 6) ? 4 : 3;
    $stride = $width * $bytesPerPixel;
    $position = 0;
    $previousRow = array_fill(0, $stride, 0);
    $rgb = '';

    for ($rowIndex = 0; $rowIndex < $height; $rowIndex++) {
      $filter = ord($decoded[$position]);
      $position++;
      $rowData = substr($decoded, $position, $stride);
      $position += $stride;
      $row = array_values(unpack('C*', $rowData));

      foreach ($row as $byteIndex => $value) {
        $left = $byteIndex >= $bytesPerPixel ? $row[$byteIndex - $bytesPerPixel] : 0;
        $up = $previousRow[$byteIndex] ?? 0;
        $upLeft = $byteIndex >= $bytesPerPixel ? ($previousRow[$byteIndex - $bytesPerPixel] ?? 0) : 0;

        if ($filter === 1) {
          $row[$byteIndex] = ($value + $left) & 0xFF;
        } elseif ($filter === 2) {
          $row[$byteIndex] = ($value + $up) & 0xFF;
        } elseif ($filter === 3) {
          $row[$byteIndex] = ($value + intdiv($left + $up, 2)) & 0xFF;
        } elseif ($filter === 4) {
          $prediction = $left + $up - $upLeft;
          $pa = abs($prediction - $left);
          $pb = abs($prediction - $up);
          $pc = abs($prediction - $upLeft);
          $paeth = ($pa <= $pb && $pa <= $pc) ? $left : (($pb <= $pc) ? $up : $upLeft);
          $row[$byteIndex] = ($value + $paeth) & 0xFF;
        }
      }

      if ($colorType === 6) {
        for ($pixel = 0; $pixel < $stride; $pixel += 4) {
          $alpha = ($row[$pixel + 3] ?? 255) / 255;
          $red = (int) round(255 - ((255 - ($row[$pixel] ?? 255)) * $alpha));
          $green = (int) round(255 - ((255 - ($row[$pixel + 1] ?? 255)) * $alpha));
          $blue = (int) round(255 - ((255 - ($row[$pixel + 2] ?? 255)) * $alpha));
          $rgb .= chr($red) . chr($green) . chr($blue);
        }
      } else {
        foreach ($row as $byte) {
          $rgb .= chr($byte);
        }
      }

      $previousRow = $row;
    }

    return [
      'width' => $width,
      'height' => $height,
      'data' => gzcompress($rgb, 9),
    ];
  }
}

if (!class_exists('NivasityReceiptPdfComposer')) {
  class NivasityReceiptPdfComposer
  {
    private $pageWidth = 595.28;
    private $pageHeight = 841.89;
    private $contentTop = 116.0;
    private $contentBottom = 662.0;
    private $cursorY = 116.0;
    private $pages = [];
    private $content = '';
    private $headerImage = [];

    public function __construct(array $headerImage)
    {
      $this->headerImage = $headerImage;
      $this->startPage();
    }
    public function output(array $receiptData)
    {
      $purple = [0.478, 0.231, 0.451];
      $muted = [0.333, 0.333, 0.333];
      $soft = [0.973, 0.957, 1.000];
      $exportAudit = isset($receiptData['export_audit']) && is_array($receiptData['export_audit']) ? $receiptData['export_audit'] : [];
      $exportCode = trim((string) ($exportAudit['code'] ?? ''));
      $verificationUrl = trim((string) ($exportAudit['verification_url'] ?? ''));
      $exportStudentsCount = (int) ($exportAudit['students_count'] ?? 0);
      $exportTotalStudentsCount = (int) ($exportAudit['total_students_count'] ?? 0);

      $this->text(42, 98, 'Payment Receipt', 'F2', 19, $purple);
      $this->text(42, 120, 'Thank you for your purchase!', 'F1', 11, $muted);
      $this->cursorY = 142;

      if ($exportCode !== '') {
        $urlLines = $verificationUrl !== '' ? $this->wrapText($verificationUrl, 380, 8.5) : [];
        $summaryLine = '';
        if ($exportStudentsCount > 0) {
          $summaryLine = 'Students In Export: ' . number_format($exportStudentsCount);
          if ($exportTotalStudentsCount > $exportStudentsCount) {
            $summaryLine .= ' of ' . number_format($exportTotalStudentsCount) . ' currently ready for grant';
          }
        }
        $summaryLines = $summaryLine !== '' ? $this->wrapText($summaryLine, 455, 8.5) : [];
        $exportHeight = 38 + (count($urlLines) * 10) + (count($summaryLines) * 10);
        $this->ensureSpace($exportHeight + 10);
        $this->fillRect(42, $this->cursorY, 511.28, $exportHeight, [1.000, 0.965, 0.859]);
        $this->strokeRect(42, $this->cursorY, 511.28, $exportHeight, [0.949, 0.835, 0.541], 1.0);
        $lineY = $this->cursorY + 16;
        $this->text(58, $lineY, 'Export Code:', 'F2', 10.0, [0.247, 0.192, 0.102]);
        $this->text(142, $lineY, $exportCode, 'F2', 10.4, $purple);

        if (!empty($urlLines)) {
          $lineY += 16;
          $this->text(58, $lineY, 'Verify Link:', 'F2', 9.0, [0.247, 0.192, 0.102]);
          foreach ($urlLines as $index => $urlLine) {
            $this->text($index === 0 ? 128 : 128, $lineY + ($index * 10), $urlLine, 'F1', 8.5, [0.200, 0.200, 0.200]);
          }
          $lineY += count($urlLines) * 10;
        }

        foreach ($summaryLines as $index => $summaryText) {
          $this->text(58, $lineY + 8 + ($index * 10), $summaryText, 'F1', 8.5, [0.333, 0.333, 0.333]);
        }

        $this->cursorY += $exportHeight + 16;
      }

      $infoHeight = 94;
      $this->ensureSpace($infoHeight + 10);
      $this->fillRect(42, $this->cursorY, 511.28, $infoHeight, $soft);
      $this->strokeRect(42, $this->cursorY, 511.28, $infoHeight, [0.910, 0.843, 0.941], 1.0);
      $infoY = $this->cursorY + 18;
      $this->labelValueLine(58, $infoY, 'Payer Name', (string) ($receiptData['payer_name'] ?? 'Customer'));
      $this->labelValueLine(58, $infoY + 18, 'Matric No.', (string) ($receiptData['matric_no'] ?? 'N/A'));
      $this->labelValueLine(58, $infoY + 36, 'Reference', '#' . (string) ($receiptData['reference'] ?? ''));
      $this->labelValueLine(58, $infoY + 54, 'Date', (string) ($receiptData['receipt_date'] ?? ''));
      $this->labelValueLine(58, $infoY + 72, 'Total Amount', $this->formatCurrency((float) ($receiptData['total_amount'] ?? 0)));
      $this->cursorY += $infoHeight + 20;

      $items = $receiptData['items'] ?? [];
      if (!empty($items)) {
        $this->ensureSpace(58);
        $this->text(42, $this->cursorY, 'Items Purchased', 'F2', 14, $purple);
        $this->cursorY += 22;
        $this->drawTableHeader();
        foreach ($items as $item) {
          $this->drawItemRow($item);
        }
      }

      $this->ensureSpace(90);
      $this->cursorY += 12;
      $this->text(42, $this->cursorY, 'We hope you enjoy your purchase!', 'F1', 11, $muted);
      $this->text(42, $this->cursorY + 18, 'Best regards,', 'F1', 11, $muted);
      $this->text(42, $this->cursorY + 36, 'Nivasity Team', 'F2', 11, [0.200, 0.200, 0.200]);

      $this->finishPage();
      return $this->buildPdf();
    }

    private function startPage()
    {
      $this->content = '';
      $this->cursorY = $this->contentTop;
      $this->drawChrome();
    }

    private function finishPage()
    {
      $this->pages[] = $this->content;
      $this->content = '';
    }

    private function ensureSpace($height)
    {
      if (($this->cursorY + $height) <= $this->contentBottom) {
        return;
      }

      $this->finishPage();
      $this->startPage();
      $this->text(42, 98, 'Payment Receipt (continued)', 'F2', 17, [0.478, 0.231, 0.451]);
      $this->cursorY = 136;
    }

    private function drawChrome()
    {
      $this->strokeRect(28, 28, 539.28, 742, [0.478, 0.231, 0.451], 1.2);
      $this->drawImage('Im1', 42, 42, 160, 49);
      $this->line(42, 708, 553, 708, [0.925, 0.925, 0.925], 1.0);
      $this->text(42, 726, 'For any feedback or inquiries, get in touch with us at', 'F1', 9, [0.360, 0.360, 0.360]);
      $this->text(42, 742, 'support@nivasity.com', 'F2', 9, [0.478, 0.231, 0.451]);
      $this->text(42, 760, "Nivasity's services are provided by Nivasity Web Services.", 'F1', 8.5, [0.360, 0.360, 0.360]);
      $this->text(42, 774, 'A business duly incorporated under the laws of Nigeria.', 'F1', 8.5, [0.360, 0.360, 0.360]);
      $this->text(42, 792, 'Copyright © Nivasity. 2025 All rights reserved.', 'F1', 8.5, [0.360, 0.360, 0.360]);
    }

    private function drawTableHeader()
    {
      $this->fillRect(42, $this->cursorY, 511.28, 24, [0.985, 0.985, 0.985]);
      $this->line(42, $this->cursorY + 24, 553, $this->cursorY + 24, [0.910, 0.910, 0.910], 1.0);
      $this->text(48, $this->cursorY + 16, 'Item', 'F2', 10, [0.333, 0.333, 0.333]);
      $this->text(372, $this->cursorY + 16, 'Type', 'F2', 10, [0.333, 0.333, 0.333]);
      $this->textRight(544, $this->cursorY + 16, 'Price', 'F2', 10, [0.333, 0.333, 0.333]);
      $this->cursorY += 28;
    }

    private function drawItemRow(array $item)
    {
      $itemText = (string) ($item['name'] ?? '');
      $metaText = html_entity_decode((string) ($item['meta'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $itemLines = $this->wrapText($itemText, 312, 10.5);
      $metaLines = $metaText !== '' ? $this->wrapText($metaText, 312, 8.5) : [];
      $typeLines = $this->wrapText((string) ($item['type'] ?? ''), 62, 10.0);
      $priceText = $this->formatCurrency((float) ($item['price'] ?? 0));
      $rowHeight = max((count($itemLines) * 13) + (count($metaLines) * 11), count($typeLines) * 13, 18) + 10;

      $this->ensureSpace($rowHeight + 6);
      $this->line(42, $this->cursorY + $rowHeight, 553, $this->cursorY + $rowHeight, [0.950, 0.950, 0.950], 1.0);

      $lineY = $this->cursorY + 12;
      foreach ($itemLines as $line) {
        $this->text(48, $lineY, $line, 'F1', 10.5, [0.200, 0.200, 0.200]);
        $lineY += 13;
      }
      foreach ($metaLines as $line) {
        $this->text(48, $lineY, $line, 'F1', 8.5, [0.470, 0.470, 0.470]);
        $lineY += 11;
      }

      $typeY = $this->cursorY + 12;
      foreach ($typeLines as $line) {
        $this->text(372, $typeY, $line, 'F1', 10.0, [0.200, 0.200, 0.200]);
        $typeY += 13;
      }
      $this->textRight(544, $this->cursorY + 12, $priceText, 'F1', 10.0, [0.200, 0.200, 0.200]);

      $this->cursorY += $rowHeight;
    }

    private function labelValueLine($x, $y, $label, $value)
    {
      $this->text($x, $y, $label . ':', 'F2', 10, [0.220, 0.220, 0.220]);
      $this->text($x + 90, $y, $value, 'F1', 10, [0.220, 0.220, 0.220]);
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
          continue;
        }

        $chunks = str_split($word, max(1, (int) floor($maxWidth / max(1.0, $fontSize * 0.55))));
        foreach ($chunks as $chunkIndex => $chunk) {
          if ($chunkIndex < count($chunks) - 1) {
            $lines[] = $chunk;
          } else {
            $line = $chunk;
          }
        }
      }

      if ($line !== '') {
        $lines[] = $line;
      }

      return $lines === [] ? [''] : $lines;
    }

    private function estimateTextWidth($text, $fontSize)
    {
      $encoded = receipt_pdf_escape_text($text);
      return strlen($encoded) * ($fontSize * 0.52);
    }

    private function formatCurrency($amount)
    {
      return 'NGN ' . number_format($amount, 2);
    }

    private function text($x, $yTop, $text, $font, $size, $color)
    {
      $bottomY = $this->pageHeight - $yTop - ($size * 0.82);
      $escaped = receipt_pdf_escape_text($text);
      $this->content .= sprintf("q %.3F %.3F %.3F rg BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET Q\n", $color[0], $color[1], $color[2], $font, $size, $x, $bottomY, $escaped);
    }

    private function textRight($rightX, $yTop, $text, $font, $size, $color)
    {
      $width = $this->estimateTextWidth($text, $size);
      $this->text(max(42.0, $rightX - $width), $yTop, $text, $font, $size, $color);
    }

    private function line($x1, $y1Top, $x2, $y2Top, $color, $width)
    {
      $y1 = $this->pageHeight - $y1Top;
      $y2 = $this->pageHeight - $y2Top;
      $this->content .= sprintf("q %.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S Q\n", $color[0], $color[1], $color[2], $width, $x1, $y1, $x2, $y2);
    }

    private function strokeRect($x, $yTop, $width, $height, $color, $lineWidth)
    {
      $bottomY = $this->pageHeight - $yTop - $height;
      $this->content .= sprintf("q %.3F %.3F %.3F RG %.2F w %.2F %.2F %.2F %.2F re S Q\n", $color[0], $color[1], $color[2], $lineWidth, $x, $bottomY, $width, $height);
    }

    private function fillRect($x, $yTop, $width, $height, $color)
    {
      $bottomY = $this->pageHeight - $yTop - $height;
      $this->content .= sprintf("q %.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f Q\n", $color[0], $color[1], $color[2], $x, $bottomY, $width, $height);
    }

    private function drawImage($imageName, $x, $yTop, $width, $height)
    {
      $bottomY = $this->pageHeight - $yTop - $height;
      $this->content .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n", $width, $height, $x, $bottomY, $imageName);
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
      $headerImageData = $this->headerImage['data'];
      $headerImageId = $addObject(
        "<< /Type /XObject /Subtype /Image /Width {$this->headerImage['width']} /Height {$this->headerImage['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length "
        . strlen($headerImageData)
        . " >>\nstream\n"
        . $headerImageData
        . "\nendstream"
      );

      $pagesNodeId = $addObject('');
      $pageIds = [];

      foreach ($this->pages as $pageContent) {
        $contentStream = gzcompress($pageContent, 9);
        $contentId = $addObject("<< /Length " . strlen($contentStream) . " /Filter /FlateDecode >>\nstream\n" . $contentStream . "\nendstream");
        $pageId = $addObject(
          "<< /Type /Page /Parent {$pagesNodeId} 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] /Resources << /Font << /F1 {$fontRegularId} 0 R /F2 {$fontBoldId} 0 R >> /XObject << /Im1 {$headerImageId} 0 R >> >> /Contents {$contentId} 0 R >>"
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

if (!function_exists('receipt_pdf_render')) {
  function receipt_pdf_render(array $receiptData, $logoPath = null)
  {
    $resolvedHeaderLogoPath = $logoPath ?: dirname(__DIR__) . '/assets/images/nivasity-main.png';
    $headerImage = receipt_pdf_parse_png($resolvedHeaderLogoPath);
    $composer = new NivasityReceiptPdfComposer($headerImage);
    return $composer->output($receiptData);
  }
}