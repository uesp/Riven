<?php
class TableCell
{
	private $attribs;
	private $colspan;
	private $content;
	private $isHeader;
	/**
	 * $parent
	 *
	 * @var self
	 */
	private $parent;
	private $rowspan;
	private $rowspanModified;

	private static $colSpanRegex = '#\bcolspan\s*=\s*([\'"]?)(?<span>\d+)\1#';
	private static $rowSpanRegex = '#\browspan\s*=\s*([\'"]?)(?<span>\d+)\1#';


	/**
	 * Creates a new instance of a TableCell.
	 *
	 * @param string $attribs Cell attributes.
	 * @param bool $isHeader Whether the cell a header.
	 * @param ?TableCell $parent The parent cell for cells that span multiple rows or columns.
	 * @param int $colspan How many columns the cell spans.
	 * @param int $rowspan How many rows the cell spans.
	 *
	 */
	private function __construct(?string $content, string $attribs, bool $isHeader, ?TableCell $parent, int $colspan, int $rowspan)
	{
		$this->attribs = $attribs;
		$this->content = $content;
		$this->isHeader = $isHeader;
		$this->parent = $parent;
		$this->colspan = $colspan;
		$this->rowspan = $rowspan;
	}

	/**
	 * Creates a new instance of a TableCell from a Regex match with named groups.
	 *
	 * @param array $match
	 *
	 * @return ?TableCell
	 *
	 */
	public static function FromMatch(array $match): ?TableCell
	{
		if (!isset($match)) {
			return null;
		}

		$attribs = trim($match['attribs']);
		preg_match(self::$colSpanRegex, $attribs, $colspan);
		$colspan = $colspan ? intval($colspan['span']) : 1;
		preg_match(self::$rowSpanRegex, $attribs, $rowspan);
		$rowspan = $rowspan ? intval($rowspan['span']) : 1;

		return new TableCell($match['content'], $attribs, $match['name'] === 'th', null, $colspan, $rowspan);
	}

	/**
	 * Creates a TableCell that points to its parent cell for column/row spans.
	 *
	 * @param TableCell $parent
	 *
	 * @return ?TableCell
	 *
	 */
	public static function SpanChild(TableCell $parent): ?TableCell
	{
		return isset($parent)
			? new TableCell('', $parent->attribs, $parent->isHeader, $parent, 0, 0)
			: null;
	}

	/**
	 * Reduces the parent rowspan by one or, if there's no parent, the current rowspan.
	 *
	 * @return void
	 *
	 */
	public function decrementRowspan(): void
	{
		// Because of the possibility of repeated updates, the rowspan value is altered on its own and the attributes
		// updated only when actually called for.
		if ($this->parent) {
			$this->parent->decrementRowspan();
		} else {
			$this->rowspan--;
			$this->rowspanModified = true;
		}
	}

	/**
	 * Gets the colspan property (<td colspan=#>)
	 *
	 * @return int
	 *
	 */
	public function getColspan(): int
	{
		return $this->colspan;
	}

	/**
	 * Gets the content property (<td>content</td>).
	 *
	 * @return string
	 *
	 */
	public function getContent(): string
	{
		return $this->content;
	}

	/**
	 * Gets whether the cell is a header (<th>) or a regular cell (<td>).
	 *
	 * @return bool
	 *
	 */
	public function getIsHeader(): bool
	{
		return $this->isHeader;
	}

	/**
	 * Gets the parent TableCell for a colspan or rowspan.
	 *
	 * @return ?TableCell
	 *
	 */
	public function getParent(): ?TableCell
	{
		return $this->parent;
	}

	/**
	 * Gets the rowspan property (<td rowspan=#>).
	 *
	 * @return int
	 *
	 */
	public function getRowspan(): int
	{
		$obj = $this->parent ? $this->parent : $this;
		return $obj->rowspan;
	}

	/**
	 * Serializes the TableCell to HTML.
	 *
	 * @return string
	 *
	 */
	public function toHtml(): string
	{
		if ($this->parent) {
			return '';
		}

		$this->updateRowSpan();
		$name = $this->isHeader ? 'th' : 'td';
		$attribs = strlen($this->attribs) > 0 ? ' ' . $this->attribs : '';
		return "<$name$attribs>$this->content</$name>";
	}

	/**
	 * Updates the rowspan portion of the attribs based on the current rowspan property.
	 *
	 * @return void
	 *
	 */
	private function updateRowSpan(): void
	{
		if ($this->rowspanModified) {
			$this->attribs = preg_replace(self::$rowSpanRegex, $this->rowspan === 1 ? '' : "rowspan=$this->rowspan", $this->attribs);
			$this->rowspanModified = false;
		}
	}
}
