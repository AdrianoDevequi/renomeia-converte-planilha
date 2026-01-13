<?php

namespace App;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class FileProcessor
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    public function processUpload(array $file): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Upload failed with error code: " . $file['error']);
        }

        $originalName = $file['name'];
        $tmpPath = $file['tmp_name'];

        // 1. Get definition from DB
        $definition = $this->db->getFileDefinition($originalName);

        if (!$definition) {
            throw new RuntimeException("Arquivo não reconhecido no sistema: $originalName");
        }

        $newName = $definition['translated_name'];
        $description = $definition['description'];

        // 2. Read CSV Content
        $csvData = [];
        if (($handle = fopen($tmpPath, "r")) !== false) {
            // Detect delimiter (optional, but good practice if mixed)
            // For now assuming comma as per previous logic, but fgetcsv handles standard CSV well.
            while (($row = fgetcsv($handle, 0, ",", "\"", "\\")) !== false) {
                // Remove UTF-8 BOM if present in first cell of first row
                if (empty($csvData) && isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                }
                $csvData[] = $row;
            }
            fclose($handle);
        }

        // Filter empty rows and trim cells
        $csvData = array_filter($csvData, function ($row) {
            return !empty(array_filter($row, function ($cell) {
                return trim($cell) !== '';
            }));
        });
        // Re-index array
        $csvData = array_values($csvData);

        if (empty($csvData)) {
            throw new RuntimeException("Arquivo CSV não contém dados válidos.");
        }

        // Analyze Headers to find Title and Description columns
        $headers = $csvData[0];
        $columnMapping = []; // Maps output col index => [type, label]
        $newHeaders = [];
        $skipIndices = []; // Original indices to skip

        // Pre-pass: Identify columns to skip (redundant Length columns)
        foreach ($headers as $index => $header) {
            $headerLower = mb_strtolower($header, 'UTF-8');
            $isTitleOrDesc = false;

            if (
                str_contains($headerLower, 'título') || str_contains($headerLower, 'titulo') ||
                str_contains($headerLower, 'title') || str_contains($headerLower, 'h1') ||
                str_contains($headerLower, 'name') || str_contains($headerLower, 'nome') || $headerLower === 'sku' ||
                str_contains($headerLower, 'descrição') || str_contains($headerLower, 'descricao') ||
                str_contains($headerLower, 'description')
            ) {
                // Check next column for "Length"
                if (isset($headers[$index + 1])) {
                    $nextHeaderLower = mb_strtolower($headers[$index + 1], 'UTF-8');
                    if (str_contains($nextHeaderLower, 'length') || str_contains($nextHeaderLower, 'comprimento')) {
                        $skipIndices[$index + 1] = true;
                    }
                }
            }
        }

        // Build New Headers and Mapping
        $outColIndex = 0; // 0-indexed for this loop, maps to Excel 1-indexed later
        $originalIndexMap = []; // Maps filtered index back to original index

        foreach ($headers as $index => $header) {
            if (isset($skipIndices[$index])) {
                continue;
            }

            $newHeaders[] = $header;
            $originalIndexMap[$outColIndex] = $index;

            $headerLower = mb_strtolower($header, 'UTF-8');

            // Check for Title
            if (
                str_contains($headerLower, 'título') || str_contains($headerLower, 'titulo') ||
                str_contains($headerLower, 'title') || str_contains($headerLower, 'h1') ||
                str_contains($headerLower, 'name') || str_contains($headerLower, 'nome') || $headerLower === 'sku'
            ) {

                $columnMapping[$outColIndex] = 'title'; // store against valid output index
                $newHeaders[] = 'Qtd. Caracteres (' . $header . ')';
                $outColIndex++; // Advance for the new column we just added
            }
            // Check for Description
            elseif (
                str_contains($headerLower, 'descrição') || str_contains($headerLower, 'descricao') ||
                str_contains($headerLower, 'description')
            ) {

                $columnMapping[$outColIndex] = 'description';
                $newHeaders[] = 'Qtd. Caracteres (' . $header . ')';
                $outColIndex++; // Advance for new column
            }

            $outColIndex++;
        }

        // 3. Create Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // --- ROW 1: LOGO ---
        // Merge Row 1 for Logo
        $sheet->mergeCells('A1:Z1');
        $sheet->getRowDimension(1)->setRowHeight(45); // Tighter height for Logo

        $logoPath = __DIR__ . '/../assets/logo.png';
        if (file_exists($logoPath)) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Logo');
            $drawing->setDescription('Logo');
            $drawing->setPath($logoPath);
            $drawing->setWidth(200); // Limit width to 200px
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(10);
            $drawing->setOffsetY(5); // Less padding
            $drawing->setWorksheet($sheet);
        }

        // Style Row 1 (Gray Background)
        $sheet->getStyle('A1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');

        // --- ROW 2: DESCRIPTION ---
        // Inject Description at Row 2
        $sheet->setCellValue('A2', $description);
        $sheet->mergeCells('A2:Z2');

        // Styles for Description (Row 2)
        $styleArray = [
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true, // ENABLE WRAP TEXT
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE0E0E0'],
            ],
        ];
        $sheet->getStyle('A2')->applyFromArray($styleArray);

        // Let Row 2 auto-size to fit wrapped text
        // Auto-height on merged cells is tricky. We calculate manually.
        $descLength = mb_strlen($description);
        $charsPerLine = 130; // Estimate chars per line for merged A:Z
        $estLines = ceil($descLength / $charsPerLine);
        $estLines = max(1, $estLines); // At least 1 line

        $lineHeight = 15; // Pixel height per line
        $padding = 10;

        $finalHeight = ($estLines * $lineHeight) + $padding;
        $sheet->getRowDimension(2)->setRowHeight($finalHeight);

        // 5. Insert Headers at Row 3
        $col = 1;
        $titleCountCols = [];
        $descCountCols = [];

        foreach ($newHeaders as $header) {
            $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '3';
            $sheet->setCellValue($coordinate, $header);

            // Style for Column Headers
            $sheet->getStyle($coordinate)->getFont()->setBold(true);

            // Check if this current column is a "Character Count" column we just added
            // We identify it by checking if it was NOT in the original map but we are iterating in sync?
            // Easier: Re-iterate logic or store indices.
            // Let's store the column letter for conditional formatting later.
            // Logic: If the PREVIOUS logic added a column, this is it.
            // Actually, let's track it in the rewrite loop below.

            $col++;
        }

        // Freeze top 3 rows (Logo + Description + Header)
        $sheet->freezePane('A4');

        // 6. Insert Data starting at Row 4
        $row = 4;
        // Skip original header in loop
        $dataRows = array_slice($csvData, 1);

        foreach ($dataRows as $rowData) {
            $col = 1;
            $processedCols = 0;

            // Iterate through original data, but skip indices
            foreach ($rowData as $originalIndex => $cellData) {
                if (isset($skipIndices[$originalIndex])) {
                    continue;
                }

                // Write original data
                $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
                $sheet->setCellValue($coordinate, $cellData);
                $col++;

                // Check if we need to insert a formula column
                // We check if the *current filtered column index* triggered a mapping
                // Note: $processedCols tracks the index in the *filtered* list (roughly)
                // Actually, relies on $columnMapping which was built on outColIndex
                // $col was just incremented. The mapping was stored at the index of the ORIGINAL column in the OUTPUT.
                // Output Column Index of the data we just wrote = $col - 1

                // Let's use a separate counter for consistency with header build
                // actually relies on $processedCols which mirrors $outColIndex logic

                if (isset($columnMapping[$processedCols])) {
                    $type = $columnMapping[$processedCols];

                    // Formula
                    $prevColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 1);
                    $currColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);

                    $formula = "=LEN({$prevColLetter}{$row})";
                    $sheet->setCellValue("{$currColLetter}{$row}", $formula);

                    if ($type === 'title') {
                        $titleCountCols[$currColLetter] = true;
                    } else {
                        $descCountCols[$currColLetter] = true;
                    }

                    $col++;
                    $processedCols++; // Skip the count column in our virtual index
                }

                $processedCols++;
            }
            $row++;
        }

        $lastRow = $row - 1;

        // 7. Apply Conditional Formatting
        // Title Rules: < 50 (Yellow), 50-60 (Green), > 60 (Red)
        foreach (array_keys($titleCountCols) as $colLetter) {
            $range = $colLetter . '4:' . $colLetter . $lastRow;
            $this->applyConditionalFormatting($sheet, $range, [
                'min' => 50,
                'max' => 60, // Green range
                'warn_below' => 50, // Yellow
                'warn_above' => 60 // Red
            ]);
        }

        // Description Rules: < 150 (Yellow), 150-160 (Green), > 160 (Red)
        foreach (array_keys($descCountCols) as $colLetter) {
            $range = $colLetter . '4:' . $colLetter . $lastRow;
            $this->applyConditionalFormatting($sheet, $range, [
                'min' => 150,
                'max' => 160,
                'warn_below' => 150,
                'warn_above' => 160
            ]);
        }

        // Adjust Column Widths and Formatting
        foreach (range(1, $col - 1) as $c) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $headerVal = $sheet->getCell($colLetter . '3')->getValue();
            $headerLower = mb_strtolower((string) $headerVal, 'UTF-8');

            $isCount = str_contains($headerVal, 'Qtd. Caracteres');
            $isLink = str_contains($headerLower, 'address') || str_contains($headerLower, 'url') ||
                str_contains($headerLower, 'link') || str_contains($headerLower, 'endereço');

            // Heuristic for numeric/short codes: SKU, EAN, NCM, ID, Price, Preço, Qtd
            $isNumeric = $headerLower === 'sku' || $headerLower === 'id' ||
                str_contains($headerLower, 'ean') || str_contains($headerLower, 'ncm') ||
                str_contains($headerLower, 'price') || str_contains($headerLower, 'preço') ||
                str_contains($headerLower, 'qtd') || str_contains($headerLower, 'quantity');

            // Determine Width and Wrapping
            if ($isCount) {
                // Count Columns: Narrow, Wrap Header
                $sheet->getColumnDimension($colLetter)->setWidth(12);
                $shouldWrap = true;
            } elseif ($isNumeric) {
                // Numeric/Code Columns: Narrow, No Wrap
                $sheet->getColumnDimension($colLetter)->setWidth(15);
                $shouldWrap = false;
            } elseif ($isLink) {
                // Link Columns: Extra Wide, No Wrap
                $sheet->getColumnDimension($colLetter)->setWidth(70);
                $shouldWrap = false;
            } else {
                // Content Columns (Title, Description, etc): Moderate width, NO wrapping (Matches Screaming Frog)
                $sheet->getColumnDimension($colLetter)->setWidth(60); // Slightly wider to show context
                $shouldWrap = false;
            }

            // Apply Alignments
            // Headers (Row 3) - NO wrap, Bold
            $sheet->getStyle($colLetter . '3')->getAlignment()->setWrapText(false);
            $sheet->getStyle($colLetter . '3')->getFont()->setBold(true);

            // Data (Row 4+) - Apply determining wrapping
            $sheet->getStyle($colLetter . '4:' . $colLetter . $lastRow)
                ->getAlignment()->setWrapText($shouldWrap);

            $sheet->getStyle($colLetter . '3:' . $colLetter . $lastRow)
                ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }

        // 8. Save
        $writer = new Xlsx($spreadsheet);
        $outputDir = __DIR__ . '/../processed_files/';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $outputFilename = $newName . '.xlsx';
        $outputPath = $outputDir . $outputFilename;
        $writer->save($outputPath);

        return $outputFilename;
    }

    private function applyConditionalFormatting($sheet, $range, $rules)
    {
        $conditionalStyles = [];

        // RED (> max)
        $redCondition = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $redCondition->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $redCondition->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_GREATERTHAN);
        $redCondition->addCondition($rules['warn_above']);
        $redCondition->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getEndColor()->setARGB('FFFFCCCC');
        $conditionalStyles[] = $redCondition;

        // YELLOW (< min)
        $yellowCondition = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $yellowCondition->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $yellowCondition->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_LESSTHAN);
        $yellowCondition->addCondition($rules['warn_below']);
        $yellowCondition->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getEndColor()->setARGB('FFFFFFCC');
        $conditionalStyles[] = $yellowCondition;

        // GREEN (Between min and max)
        $greenCondition = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $greenCondition->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $greenCondition->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_BETWEEN);
        $greenCondition->addCondition($rules['min']);
        $greenCondition->addCondition($rules['max']);
        $greenCondition->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getEndColor()->setARGB('FFCCFFCC');
        $conditionalStyles[] = $greenCondition;

        $sheet->getStyle($range)->setConditionalStyles($conditionalStyles);
    }
}
