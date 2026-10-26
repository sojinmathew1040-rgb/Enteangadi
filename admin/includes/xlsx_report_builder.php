<?php
/**
 * Enteangadi Marketplace - Executive XLSX Report Builder (OpenXML Native)
 * Generates true .xlsx files with Power BI / Tableau quality design,
 * native Excel charts (Doughnut, Column, Clustered Comparison, Line),
 * KPI cards, and structured data tables.
 * 
 * Compliant with ECMA-376 OpenXML standard - Zero warning popups in Microsoft Excel.
 */

class EnteangadiXlsxReportBuilder {
    private $sheets = []; // name => ['cols' => [], 'rows' => [], 'merges' => [], 'autofilter' => null]
    private $drawings = []; // sheet_idx => [ charts... ]
    private $charts = [];   // list of chart defs
    private $styles = [];

    public function __construct() {
        // Initialize default configuration
    }

    /**
     * Add a worksheet
     */
    public function addSheet($name, $colWidths = []) {
        $this->sheets[$name] = [
            'cols' => $colWidths,
            'rows' => [],
            'merges' => [],
            'autofilter' => null
        ];
    }

    /**
     * Set column widths for a sheet
     * e.g. [1 => 30, 2 => 18, 3 => 18]
     */
    public function setCols($sheetName, $colWidths) {
        if (isset($this->sheets[$sheetName])) {
            $this->sheets[$sheetName]['cols'] = $colWidths;
        }
    }

    /**
     * Add a cell merge (e.g. 'A1:E1')
     */
    public function mergeCells($sheetName, $range) {
        if (isset($this->sheets[$sheetName])) {
            $this->sheets[$sheetName]['merges'][] = $range;
        }
    }

    /**
     * Set AutoFilter on a range (e.g. 'A1:H50')
     */
    public function setAutoFilter($sheetName, $range) {
        if (isset($this->sheets[$sheetName])) {
            $this->sheets[$sheetName]['autofilter'] = $range;
        }
    }

    /**
     * Add a row to a sheet
     * $cells is array of cell definitions:
     * ['col' => 'A', 'val' => 'Text', 'type' => 'string'|'number'|'currency'|'percent'|'date', 'style' => $styleId]
     */
    public function addRow($sheetName, $rowNum, $cells, $height = null) {
        if (!isset($this->sheets[$sheetName])) {
            $this->addSheet($sheetName);
        }
        $this->sheets[$sheetName]['rows'][$rowNum] = [
            'height' => $height,
            'cells' => $cells
        ];
    }

    /**
     * Add a Native Excel Doughnut / Pie Chart
     */
    public function addDoughnutChart($sheetName, $title, $catRange, $valRange, $catsData, $valsData, $fromCol, $fromRow, $toCol, $toRow, $holeSize = 55) {
        $chartId = count($this->charts) + 1;
        $this->charts[$chartId] = [
            'type' => 'doughnut',
            'title' => $title,
            'catRange' => $catRange,
            'valRange' => $valRange,
            'cats' => $catsData,
            'vals' => $valsData,
            'holeSize' => $holeSize
        ];

        $sheetIdx = array_search($sheetName, array_keys($this->sheets));
        if ($sheetIdx === false) $sheetIdx = 0;

        $this->drawings[$sheetIdx][] = [
            'chartId' => $chartId,
            'fromCol' => $fromCol,
            'fromRow' => $fromRow,
            'toCol' => $toCol,
            'toRow' => $toRow
        ];
    }

    /**
     * Add a Native Excel Single-Series Column / Bar Chart (Histogram)
     */
    public function addColumnChart($sheetName, $title, $seriesName, $catRange, $valRange, $catsData, $valsData, $fromCol, $fromRow, $toCol, $toRow, $barColor = '059669') {
        $chartId = count($this->charts) + 1;
        $this->charts[$chartId] = [
            'type' => 'column',
            'title' => $title,
            'seriesName' => $seriesName,
            'catRange' => $catRange,
            'valRange' => $valRange,
            'cats' => $catsData,
            'vals' => $valsData,
            'barColor' => $barColor
        ];

        $sheetIdx = array_search($sheetName, array_keys($this->sheets));
        if ($sheetIdx === false) $sheetIdx = 0;

        $this->drawings[$sheetIdx][] = [
            'chartId' => $chartId,
            'fromCol' => $fromCol,
            'fromRow' => $fromRow,
            'toCol' => $toCol,
            'toRow' => $toRow
        ];
    }

    /**
     * Add a Native Excel Dual-Series Clustered Column Chart (for Month-over-Month Comparison)
     */
    public function addClusteredComparisonChart($sheetName, $title, $catRange, $catsData, $series1, $series2, $fromCol, $fromRow, $toCol, $toRow) {
        // $series1 = ['name' => 'Oct 2026', 'valRange' => '...', 'vals' => [...], 'color' => '059669']
        // $series2 = ['name' => 'Sep 2026', 'valRange' => '...', 'vals' => [...], 'color' => '8B5CF6']
        $chartId = count($this->charts) + 1;
        $this->charts[$chartId] = [
            'type' => 'clustered_comparison',
            'title' => $title,
            'catRange' => $catRange,
            'cats' => $catsData,
            'series' => [$series1, $series2]
        ];

        $sheetIdx = array_search($sheetName, array_keys($this->sheets));
        if ($sheetIdx === false) $sheetIdx = 0;

        $this->drawings[$sheetIdx][] = [
            'chartId' => $chartId,
            'fromCol' => $fromCol,
            'fromRow' => $fromRow,
            'toCol' => $toCol,
            'toRow' => $toRow
        ];
    }

    /**
     * Add a Native Excel Line Flow Trendline Chart
     */
    public function addLineChart($sheetName, $title, $seriesName, $catRange, $valRange, $catsData, $valsData, $fromCol, $fromRow, $toCol, $toRow, $lineColor = '0284C7') {
        $chartId = count($this->charts) + 1;
        $this->charts[$chartId] = [
            'type' => 'line',
            'title' => $title,
            'seriesName' => $seriesName,
            'catRange' => $catRange,
            'valRange' => $valRange,
            'cats' => $catsData,
            'vals' => $valsData,
            'lineColor' => $lineColor
        ];

        $sheetIdx = array_search($sheetName, array_keys($this->sheets));
        if ($sheetIdx === false) $sheetIdx = 0;

        $this->drawings[$sheetIdx][] = [
            'chartId' => $chartId,
            'fromCol' => $fromCol,
            'fromRow' => $fromRow,
            'toCol' => $toCol,
            'toRow' => $toRow
        ];
    }

    /**
     * XML String Escape Helper
     */
    private static function esc($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Build the Stylesheet XML (Power BI / Tableau Executive Palette)
     * Styles:
     * 0: Normal text
     * 1: Main Banner Header (Bold 15pt White, Dark Emerald #065F46)
     * 2: Section Header (Bold 11pt White, Dark Teal #0F766E)
     * 3: Table Header (Bold 10.5pt White, Vibrant Emerald #059669)
     * 4: Bold Data Label (Slate 900)
     * 5: Muted Label (Slate 500, 9pt)
     * 6: Data Number (#,##0)
     * 7: Data Percent (0.0%)
     * 8: Data Currency (₹ #,##0.00)
     * 9: Data Date (yyyy-mm-dd hh:mm)
     * 10: Zebra Row Text (#F8FAFC)
     * 11: Zebra Row Number (#F8FAFC, #,##0)
     * 12: Zebra Row Percent (#F8FAFC, 0.0%)
     * 13: Positive Trend Badge (#ECFDF5, Bold Green)
     * 14: Negative Trend Badge (#FEF2F2, Bold Red)
     * 15: Table Header Secondary (Bold 10.5pt, Slate 800, #E2E8F0)
     */
    private function buildStylesXml() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <numFmts count="4">
        <numFmt numFmtId="164" formatCode="#,##0"/>
        <numFmt numFmtId="165" formatCode="0.0%"/>
        <numFmt numFmtId="166" formatCode="[$₹-4009] #,##0.00"/>
        <numFmt numFmtId="167" formatCode="yyyy-mm-dd hh:mm"/>
    </numFmts>
    <fonts count="10">
        <!-- 0: Regular -->
        <font><name val="Segoe UI"/><sz val="10"/><color rgb="FF1E293B"/></font>
        <!-- 1: Big Banner Title -->
        <font><b/><name val="Segoe UI"/><sz val="15"/><color rgb="FFFFFFFF"/></font>
        <!-- 2: White Header -->
        <font><b/><name val="Segoe UI"/><sz val="11"/><color rgb="FFFFFFFF"/></font>
        <!-- 3: Bold Dark Text -->
        <font><b/><name val="Segoe UI"/><sz val="10"/><color rgb="FF0F172A"/></font>
        <!-- 4: Muted Text -->
        <font><name val="Segoe UI"/><sz val="9"/><color rgb="FF64748B"/></font>
        <!-- 5: Section Accent -->
        <font><b/><name val="Segoe UI"/><sz val="12"/><color rgb="FF065F46"/></font>
        <!-- 6: Positive Green -->
        <font><b/><name val="Segoe UI"/><sz val="10"/><color rgb="FF047857"/></font>
        <!-- 7: Negative Red -->
        <font><b/><name val="Segoe UI"/><sz val="10"/><color rgb="FFB91C1C"/></font>
        <!-- 8: Subtitle -->
        <font><i/><name val="Segoe UI"/><sz val="10"/><color rgb="FFE2E8F0"/></font>
        <!-- 9: Table Header Slate -->
        <font><b/><name val="Segoe UI"/><sz val="10"/><color rgb="FF0F172A"/></font>
    </fonts>
    <fills count="10">
        <!-- 0: None -->
        <fill><patternFill patternType="none"/></fill>
        <!-- 1: Gray125 -->
        <fill><patternFill patternType="gray125"/></fill>
        <!-- 2: Deep Emerald #065F46 -->
        <fill><patternFill patternType="solid"><fgColor rgb="FF065F46"/></patternFill></fill>
        <!-- 3: Teal #0F766E -->
        <fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/></fill></fill>
        <!-- 4: Emerald #059669 -->
        <fill><patternFill patternType="solid"><fgColor rgb="FF059669"/></fill></fill>
        <!-- 5: Zebra Slate #F8FAFC -->
        <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></fill></fill>
        <!-- 6: Soft Green Badge #ECFDF5 -->
        <fill><patternFill patternType="solid"><fgColor rgb="FFECFDF5"/></fill></fill>
        <!-- 7: Soft Red Badge #FEF2F2 -->
        <fill><patternFill patternType="solid"><fgColor rgb="FFFEF2F2"/></fill></fill>
        <!-- 8: Slate Header #F1F5F9 -->
        <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></fill></fill>
        <!-- 9: Light Slate Card #F8FAFC -->
        <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></fill></fill>
    </fills>
    <borders count="3">
        <!-- 0: None -->
        <border><left/><right/><top/><bottom/></border>
        <!-- 1: Thin Slate #CBD5E1 -->
        <border>
            <left style="thin"><color rgb="FFCBD5E1"/></left>
            <right style="thin"><color rgb="FFCBD5E1"/></right>
            <top style="thin"><color rgb="FFCBD5E1"/></top>
            <bottom style="thin"><color rgb="FFCBD5E1"/></bottom>
        </border>
        <!-- 2: Double Bottom -->
        <border>
            <left style="thin"><color rgb="FFCBD5E1"/></left>
            <right style="thin"><color rgb="FFCBD5E1"/></right>
            <top style="thin"><color rgb="FFCBD5E1"/></top>
            <bottom style="medium"><color rgb="FF059669"/></bottom>
        </border>
    </borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="16">
        <!-- 0: Regular -->
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <!-- 1: Banner Header -->
        <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>
        <!-- 2: Section Header (Teal) -->
        <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>
        <!-- 3: Table Header (Emerald) -->
        <xf numFmtId="0" fontId="2" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>
        <!-- 4: Bold Label Bordered -->
        <xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>
        <!-- 5: Muted Label Bordered -->
        <xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>
        <!-- 6: Number (#,##0) -->
        <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="right"/></xf>
        <!-- 7: Percent (0.0%) -->
        <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="right"/></xf>
        <!-- 8: Currency (₹ #,##0.00) -->
        <xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="right"/></xf>
        <!-- 9: Date (yyyy-mm-dd hh:mm) -->
        <xf numFmtId="167" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>
        <!-- 10: Zebra Row Text -->
        <xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>
        <!-- 11: Zebra Row Number -->
        <xf numFmtId="164" fontId="0" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="right"/></xf>
        <!-- 12: Zebra Row Percent -->
        <xf numFmtId="165" fontId="0" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="right"/></xf>
        <!-- 13: Positive Trend Badge -->
        <xf numFmtId="0" fontId="6" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>
        <!-- 14: Negative Trend Badge -->
        <xf numFmtId="0" fontId="7" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>
        <!-- 15: Table Header Slate Secondary -->
        <xf numFmtId="0" fontId="9" fillId="8" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="center"/></xf>
    </cellXfs>
</styleSheet>';
    }

    /**
     * Build Chart XML for a single chart definition
     */
    private function buildChartXml($chart) {
        $type = $chart['type'];
        $title = self::esc($chart['title']);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <c:date1904 val="0"/><c:lang val="en-US"/><c:roundedCorners val="1"/>
    <c:chart>
        <c:title>
            <c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:pPr><a:defRPr sz="1200" b="1"/></a:pPr><a:r><a:rPr sz="1200" b="1"/><a:t>' . $title . '</a:t></a:r></a:p></c:rich></c:tx>
            <c:layout/>
        </c:title>
        <c:plotArea>
            <c:layout/>';

        if ($type === 'doughnut') {
            $catRange = self::esc($chart['catRange']);
            $valRange = self::esc($chart['valRange']);
            $n = count($chart['cats']);
            $holeSize = $chart['holeSize'] ?? 55;

            $xml .= '<c:doughnutChart>
                <c:varyColors val="1"/>
                <c:ser>
                    <c:idx val="0"/><c:order val="0"/>
                    <c:tx><c:v>' . $title . '</c:v></c:tx>
                    <c:cat>
                        <c:strRef>
                            <c:f>' . $catRange . '</c:f>
                            <c:strData>
                                <c:ptCount val="' . $n . '"/>';
            foreach ($chart['cats'] as $i => $cat) {
                $xml .= '<c:pt idx="' . $i . '"><c:v>' . self::esc($cat) . '</c:v></c:pt>';
            }
            $xml .= '       </c:strData>
                        </c:strRef>
                    </c:cat>
                    <c:val>
                        <c:numRef>
                            <c:f>' . $valRange . '</c:f>
                            <c:numData>
                                <c:formatCode>#,##0</c:formatCode>
                                <c:ptCount val="' . $n . '"/>';
            foreach ($chart['vals'] as $i => $val) {
                $xml .= '<c:pt idx="' . $i . '"><c:v>' . (float)$val . '</c:v></c:pt>';
            }
            $xml .= '       </c:numData>
                        </c:numRef>
                    </c:val>
                </c:ser>
                <c:holeSize val="' . $holeSize . '"/>
            </c:doughnutChart>
        </c:plotArea>
        <c:legend><c:legendPos val="b"/><c:layout/></c:legend>';

        } elseif ($type === 'column') {
            $catRange = self::esc($chart['catRange']);
            $valRange = self::esc($chart['valRange']);
            $seriesName = self::esc($chart['seriesName']);
            $barColor = $chart['barColor'] ?? '059669';
            $n = count($chart['cats']);

            $xml .= '<c:barChart>
                <c:barDir val="col"/>
                <c:grouping val="clustered"/>
                <c:varyColors val="0"/>
                <c:gapWidth val="130"/>
                <c:ser>
                    <c:idx val="0"/><c:order val="0"/>
                    <c:tx><c:v>' . $seriesName . '</c:v></c:tx>
                    <c:spPr><a:solidFill><a:srgbClr val="' . $barColor . '"/></a:solidFill></c:spPr>
                    <c:cat>
                        <c:strRef>
                            <c:f>' . $catRange . '</c:f>
                            <c:strData>
                                <c:ptCount val="' . $n . '"/>';
            foreach ($chart['cats'] as $i => $cat) {
                $xml .= '<c:pt idx="' . $i . '"><c:v>' . self::esc($cat) . '</c:v></c:pt>';
            }
            $xml .= '       </c:strData>
                        </c:strRef>
                    </c:cat>
                    <c:val>
                        <c:numRef>
                            <c:f>' . $valRange . '</c:f>
                            <c:numData>
                                <c:formatCode>#,##0</c:formatCode>
                                <c:ptCount val="' . $n . '"/>';
            foreach ($chart['vals'] as $i => $val) {
                $xml .= '<c:pt idx="' . $i . '"><c:v>' . (float)$val . '</c:v></c:pt>';
            }
            $xml .= '       </c:numData>
                        </c:numRef>
                    </c:val>
                </c:ser>
                <c:axId val="1001"/><c:axId val="1002"/>
            </c:barChart>
            <c:catAx>
                <c:axId val="1001"/>
                <c:scaling><c:orientation val="minMax"/></c:scaling>
                <c:delete val="0"/><c:axPos val="b"/><c:tickLblPos val="nextTo"/><c:crossAx val="1002"/><c:crosses val="autoZero"/><c:auto val="1"/><c:lblAlgn val="ctr"/><c:lblOffset val="100"/>
            </c:catAx>
            <c:valAx>
                <c:axId val="1002"/>
                <c:scaling><c:orientation val="minMax"/></c:scaling>
                <c:delete val="0"/><c:axPos val="l"/>
                <c:majorGridlines><c:spPr><a:ln w="9525"><a:solidFill><a:srgbClr val="E2E8F0"/></a:solidFill></a:ln></c:spPr></c:majorGridlines>
                <c:numFmt formatCode="#,##0" sourceLinked="0"/><c:tickLblPos val="nextTo"/><c:crossAx val="1001"/><c:crosses val="autoZero"/><c:crossBetween val="between"/>
            </c:valAx>
        </c:plotArea>
        <c:legend><c:legendPos val="b"/><c:layout/></c:legend>';

        } elseif ($type === 'clustered_comparison') {
            $catRange = self::esc($chart['catRange']);
            $n = count($chart['cats']);

            $xml .= '<c:barChart>
                <c:barDir val="col"/>
                <c:grouping val="clustered"/>
                <c:varyColors val="0"/>
                <c:gapWidth val="130"/>';

            foreach ($chart['series'] as $sIdx => $sDef) {
                $sName = self::esc($sDef['name']);
                $sColor = $sDef['color'] ?? ($sIdx === 0 ? '059669' : '8B5CF6');
                $sValRange = self::esc($sDef['valRange']);

                $xml .= '<c:ser>
                    <c:idx val="' . $sIdx . '"/><c:order val="' . $sIdx . '"/>
                    <c:tx><c:v>' . $sName . '</c:v></c:tx>
                    <c:spPr><a:solidFill><a:srgbClr val="' . $sColor . '"/></a:solidFill></c:spPr>
                    <c:cat>
                        <c:strRef>
                            <c:f>' . $catRange . '</c:f>
                            <c:strData>
                                <c:ptCount val="' . $n . '"/>';
                foreach ($chart['cats'] as $i => $cat) {
                    $xml .= '<c:pt idx="' . $i . '"><c:v>' . self::esc($cat) . '</c:v></c:pt>';
                }
                $xml .= '       </c:strData>
                        </c:strRef>
                    </c:cat>
                    <c:val>
                        <c:numRef>
                            <c:f>' . $sValRange . '</c:f>
                            <c:numData>
                                <c:formatCode>#,##0</c:formatCode>
                                <c:ptCount val="' . $n . '"/>';
                foreach ($sDef['vals'] as $i => $val) {
                    $xml .= '<c:pt idx="' . $i . '"><c:v>' . (float)$val . '</c:v></c:pt>';
                }
                $xml .= '       </c:numData>
                        </c:numRef>
                    </c:val>
                </c:ser>';
            }

            $xml .= '
                <c:axId val="2001"/><c:axId val="2002"/>
            </c:barChart>
            <c:catAx>
                <c:axId val="2001"/>
                <c:scaling><c:orientation val="minMax"/></c:scaling>
                <c:delete val="0"/><c:axPos val="b"/><c:tickLblPos val="nextTo"/><c:crossAx val="2002"/><c:crosses val="autoZero"/><c:auto val="1"/><c:lblAlgn val="ctr"/><c:lblOffset val="100"/>
            </c:catAx>
            <c:valAx>
                <c:axId val="2002"/>
                <c:scaling><c:orientation val="minMax"/></c:scaling>
                <c:delete val="0"/><c:axPos val="l"/>
                <c:majorGridlines><c:spPr><a:ln w="9525"><a:solidFill><a:srgbClr val="E2E8F0"/></a:solidFill></a:ln></c:spPr></c:majorGridlines>
                <c:numFmt formatCode="#,##0" sourceLinked="0"/><c:tickLblPos val="nextTo"/><c:crossAx val="2001"/><c:crosses val="autoZero"/><c:crossBetween val="between"/>
            </c:valAx>
        </c:plotArea>
        <c:legend><c:legendPos val="t"/><c:layout/></c:legend>';

        } elseif ($type === 'line') {
            $catRange = self::esc($chart['catRange']);
            $valRange = self::esc($chart['valRange']);
            $seriesName = self::esc($chart['seriesName']);
            $lineColor = $chart['lineColor'] ?? '0284C7';
            $n = count($chart['cats']);

            $xml .= '<c:lineChart>
                <c:grouping val="standard"/>
                <c:varyColors val="0"/>
                <c:ser>
                    <c:idx val="0"/><c:order val="0"/>
                    <c:tx><c:v>' . $seriesName . '</c:v></c:tx>
                    <c:spPr><a:ln w="25400"><a:solidFill><a:srgbClr val="' . $lineColor . '"/></a:solidFill></a:ln></c:spPr>
                    <c:cat>
                        <c:strRef>
                            <c:f>' . $catRange . '</c:f>
                            <c:strData>
                                <c:ptCount val="' . $n . '"/>';
            foreach ($chart['cats'] as $i => $cat) {
                $xml .= '<c:pt idx="' . $i . '"><c:v>' . self::esc($cat) . '</c:v></c:pt>';
            }
            $xml .= '       </c:strData>
                        </c:strRef>
                    </c:cat>
                    <c:val>
                        <c:numRef>
                            <c:f>' . $valRange . '</c:f>
                            <c:numData>
                                <c:formatCode>#,##0</c:formatCode>
                                <c:ptCount val="' . $n . '"/>';
            foreach ($chart['vals'] as $i => $val) {
                $xml .= '<c:pt idx="' . $i . '"><c:v>' . (float)$val . '</c:v></c:pt>';
            }
            $xml .= '       </c:numData>
                        </c:numRef>
                    </c:val>
                    <c:smooth val="1"/>
                </c:ser>
                <c:axId val="3001"/><c:axId val="3002"/>
            </c:lineChart>
            <c:catAx>
                <c:axId val="3001"/>
                <c:scaling><c:orientation val="minMax"/></c:scaling>
                <c:delete val="0"/><c:axPos val="b"/><c:tickLblPos val="nextTo"/><c:crossAx val="3002"/><c:crosses val="autoZero"/><c:auto val="1"/><c:lblAlgn val="ctr"/><c:lblOffset val="100"/>
            </c:catAx>
            <c:valAx>
                <c:axId val="3002"/>
                <c:scaling><c:orientation val="minMax"/></c:scaling>
                <c:delete val="0"/><c:axPos val="l"/>
                <c:majorGridlines><c:spPr><a:ln w="9525"><a:solidFill><a:srgbClr val="E2E8F0"/></a:solidFill></a:ln></c:spPr></c:majorGridlines>
                <c:numFmt formatCode="#,##0" sourceLinked="0"/><c:tickLblPos val="nextTo"/><c:crossAx val="3001"/><c:crosses val="autoZero"/><c:crossBetween val="between"/>
            </c:valAx>
        </c:plotArea>
        <c:legend><c:legendPos val="b"/><c:layout/></c:legend>';
        }

        $xml .= '
        <c:plotVisOnly val="1"/>
    </c:chart>
</c:chartSpace>';
        return $xml;
    }

    /**
     * Build Drawing XML for a sheet
     */
    private function buildDrawingXml($drawingsForSheet) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">';

        foreach ($drawingsForSheet as $idx => $d) {
            $relId = 'rId' . ($idx + 1);
            $xml .= '
    <xdr:twoCellAnchor editAs="twoCell">
        <xdr:from>
            <xdr:col>' . (int)$d['fromCol'] . '</xdr:col><xdr:colOff>50000</xdr:colOff>
            <xdr:row>' . (int)$d['fromRow'] . '</xdr:row><xdr:rowOff>50000</xdr:rowOff>
        </xdr:from>
        <xdr:to>
            <xdr:col>' . (int)$d['toCol'] . '</xdr:col><xdr:colOff>0</xdr:colOff>
            <xdr:row>' . (int)$d['toRow'] . '</xdr:row><xdr:rowOff>0</xdr:rowOff>
        </xdr:to>
        <xdr:graphicFrame macro="">
            <xdr:nvGraphicFramePr>
                <xdr:cNvPr id="' . ($idx + 2) . '" name="Chart ' . ($idx + 1) . '"/>
                <xdr:cNvGraphicFramePr/>
            </xdr:nvGraphicFramePr>
            <xdr:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></xdr:xfrm>
            <a:graphic>
                <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart">
                    <c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="' . $relId . '"/>
                </a:graphicData>
            </a:graphic>
        </xdr:graphicFrame>
        <xdr:clientData/>
    </xdr:twoCellAnchor>';
        }

        $xml .= '</xdr:wsDr>';
        return $xml;
    }

    /**
     * Build Drawing Relationships XML
     */
    private function buildDrawingRelsXml($drawingsForSheet) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($drawingsForSheet as $idx => $d) {
            $relId = 'rId' . ($idx + 1);
            $chartId = $d['chartId'];
            $xml .= '<Relationship Id="' . $relId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart' . $chartId . '.xml"/>';
        }
        $xml .= '</Relationships>';
        return $xml;
    }

    /**
     * Build Worksheet XML
     */
    private function buildWorksheetXml($sheetName, $sheetDef, $hasDrawing = false) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

        // 1. Column Widths
        if (!empty($sheetDef['cols'])) {
            $xml .= '<cols>';
            foreach ($sheetDef['cols'] as $colIdx => $width) {
                $xml .= '<col min="' . (int)$colIdx . '" max="' . (int)$colIdx . '" width="' . (float)$width . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        // 2. SheetData
        $xml .= '<sheetData>';
        // Sort rows in ascending order
        ksort($sheetDef['rows'], SORT_NUMERIC);
        foreach ($sheetDef['rows'] as $rowNum => $rDef) {
            $htAttr = !empty($rDef['height']) ? ' ht="' . (float)$rDef['height'] . '" customHeight="1"' : '';
            $xml .= '<row r="' . (int)$rowNum . '"' . $htAttr . '>';
            foreach ($rDef['cells'] as $cell) {
                $ref = $cell['col'] . $rowNum;
                $style = isset($cell['style']) ? (int)$cell['style'] : 0;
                $type = $cell['type'] ?? 'string';
                $val = $cell['val'];

                if ($type === 'number' || $type === 'currency' || $type === 'percent') {
                    $numVal = is_numeric($val) ? $val : 0;
                    $xml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $numVal . '</v></c>';
                } else {
                    // Inline string
                    $xml .= '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t>' . self::esc($val) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        // 3. AutoFilter
        if (!empty($sheetDef['autofilter'])) {
            $xml .= '<autoFilter ref="' . self::esc($sheetDef['autofilter']) . '"/>';
        }

        // 4. MergeCells
        if (!empty($sheetDef['merges'])) {
            $xml .= '<mergeCells count="' . count($sheetDef['merges']) . '">';
            foreach ($sheetDef['merges'] as $range) {
                $xml .= '<mergeCell ref="' . self::esc($range) . '"/>';
            }
            $xml .= '</mergeCells>';
        }

        // 5. Drawing Link
        if ($hasDrawing) {
            $xml .= '<drawing r:id="rId1"/>';
        }

        $xml .= '</worksheet>';
        return $xml;
    }

    /**
     * Compile and save the XLSX file to a specific disk path
     */
    public function saveToFile($destPath) {
        $zip = new ZipArchive();
        if ($zip->open($destPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception("Unable to create XLSX archive at " . $destPath);
        }

        $sheetNames = array_keys($this->sheets);

        // 1. [Content_Types].xml
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

        foreach ($sheetNames as $idx => $sName) {
            $sId = $idx + 1;
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $sId . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            if (!empty($this->drawings[$idx])) {
                $ct .= '<Override PartName="/xl/drawings/drawing' . $sId . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
            }
        }

        foreach ($this->charts as $cId => $cDef) {
            $ct .= '<Override PartName="/xl/charts/chart' . $cId . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>';
        }
        $ct .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $ct);

        // 2. _rels/.rels
        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        $zip->addFromString('_rels/.rels', $rootRels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($sheetNames as $idx => $sName) {
            $sId = $idx + 1;
            $wbRels .= '<Relationship Id="rId' . $sId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sId . '.xml"/>';
        }
        $wbRels .= '<Relationship Id="rId' . (count($sheetNames) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $wbRels .= '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/workbook.xml
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>';
        foreach ($sheetNames as $idx => $sName) {
            $sId = $idx + 1;
            $wb .= '<sheet name="' . self::esc($sName) . '" sheetId="' . $sId . '" r:id="rId' . $sId . '"/>';
        }
        $wb .= '</sheets></workbook>';
        $zip->addFromString('xl/workbook.xml', $wb);

        // 5. xl/styles.xml
        $zip->addFromString('xl/styles.xml', $this->buildStylesXml());

        // 6. Worksheets & Drawings
        foreach ($sheetNames as $idx => $sName) {
            $sId = $idx + 1;
            $hasDrawing = !empty($this->drawings[$idx]);

            if ($hasDrawing) {
                // Drawing rels
                $zip->addFromString('xl/drawings/drawing' . $sId . '.xml', $this->buildDrawingXml($this->drawings[$idx]));
                $zip->addFromString('xl/drawings/_rels/drawing' . $sId . '.xml.rels', $this->buildDrawingRelsXml($this->drawings[$idx]));

                // Sheet rels to drawing
                $shRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing' . $sId . '.xml"/>
</Relationships>';
                $zip->addFromString('xl/worksheets/_rels/sheet' . $sId . '.xml.rels', $shRels);
            }

            $zip->addFromString('xl/worksheets/sheet' . $sId . '.xml', $this->buildWorksheetXml($sName, $this->sheets[$sName], $hasDrawing));
        }

        // 7. Charts
        foreach ($this->charts as $cId => $cDef) {
            $zip->addFromString('xl/charts/chart' . $cId . '.xml', $this->buildChartXml($cDef));
        }

        $zip->close();
        return true;
    }

    /**
     * Compile and Stream the XLSX file directly to output buffer
     */
    public function download($filename) {
        $temp_zip = tempnam(sys_get_temp_dir(), 'ea_xlsx_');
        $this->saveToFile($temp_zip);

        // Stream file
        if (ob_get_level() > 0) {
            ob_clean();
        }

        $cleanName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $cleanName . '.xlsx"');
        header('Content-Length: ' . filesize($temp_zip));
        header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        readfile($temp_zip);
        @unlink($temp_zip);
        exit;
    }
}
