<?php

class TableRow
{
	#region Private Constants
	private const PREFIX_LEN = 9; // strlen(Parser::MARKER_SUFFIX) = 6 for MW < 1.27;
	#endregion

	#region Private Static Fields
	private static $cleanTypeRegex = '#\bdata-cleantype\s*=\s*([\'"]?)(?<cleantype>\w+)\1#';
	#endregion

	#region Fields
	/** @var TableCell[] $cells The cells in the row. */
	private $cells = [];

	/** @var string $openTag The full <tr> tag that started this row. */
	private $openTag;
	#endregion

	#region Public Properties
	/** @var string $cleanType The cleaning strategy to use for this row:
	 *     auto  : Use the automatic settings. Only useful to override a previous value.
	 *     clean : Always clean this row; mostly useful for debugging and format testing.
	 *     header: Treat this row like a header and show or hide it accordingly.
	 *     keep  : Always keep this row.
	 *     normal: Treat this row like a normal row and show or hide it accordingly.
	 *     tableheader: Remove this row if the entire table is being removed; otherwise always keep it.
	 */
	public $cleanType;
	public $hasContent = false;
	public $isHeader = true;
	public $width = 0;
	#endregion

	#region Constructor
	/**
	 * Creates an instance of a TableRow.
	 *
	 * @param string $openTag The full <tr> tag.
	 *
	 */
	public function __construct(string $openTag)
	{
		$this->openTag = $openTag;
		preg_match(self::$cleanTypeRegex, $openTag, $matches);
		$this->cleanType = empty($matches) ? 'auto' : $matches['cleantype'];
	}
	#endregion

	#region Public Functions
	public function addRawCells(array &$map, int $rowNum, array $rawCells, bool $cleanImages)
	{
		$cellNum = 0;
		$rowCount = count($map);
		foreach ($rawCells as $rawCell) {
			while (isset($this->cells[$cellNum])) {
				$cellNum++;
			}

			$cell = TableCell::FromMatch($rawCell, $cleanImages);
			$this->setCell($cellNum, $cell);
			$rowspan = $cell->rowspan;
			$colspan = $cell->colspan;
			#RHshow("Cell ($rowNum, $cellNum)", $cell);
			if ($rowspan > 1 || $colspan > 1) {
				$spanCell = TableCell::SpanChild($cell);
				for ($r = 0; $r < $rowspan; $r++) {
					for ($c = 0; $c < $colspan; $c++) {
						if ($r || $c) {
							$rowOffset = $rowNum + $r;
							if ($rowOffset < $rowCount) {
								$cellOffset = $cellNum + $c;
								#RHshow("Span Child ($rowOffset, $cellOffset)", $spanCell->parent);
								$map[$rowOffset]->setCell($cellOffset, $spanCell);
							}
						}
					}
				}
			}

			$cellNum++;
		}
	}

	public function decrementRowspan()
	{
		$parents = [];
		foreach ($this->cells as $cell) {
			$parent = $cell->parent;
			if ($parent && !in_array($parent, $parents, true)) {
				$parents[] = $parent;
				$parent->decrementRowspan();
			}
		}
	}

	public function getColumnCount()
	{
		return count($this->cells);
	}

	public function setCell(int $col, TableCell $cell)
	{
		$this->isHeader &= $cell->isHeader;
		$content = $cell->content;
		$hasContent = !$cell->isHeader && strlen($cell->trimmedContent);
		if (!$hasContent) {
			$startPos = strpos($content, Parser::MARKER_PREFIX);
			if ($startPos !== false) {
				$hasContent = ($startPos >= 0) && strpos($content, Parser::MARKER_SUFFIX, $startPos + self::PREFIX_LEN) >= 0;
			}
		}

		$this->hasContent |= $hasContent;

		// Count cells, treating spans as a single cell.
		if (!$cell->parent) {
			$this->width++;
		}

		$this->cells[$col] = $cell;
	}

	public function toHtml()
	{
		$output = "$this->openTag\n";
		foreach ($this->cells as $cell) {
			$html = $cell->toHtml();
			if ($html) {
				$output .= "$html\n";
			}
		}

		return $output . "</tr>\n";
	}
}
