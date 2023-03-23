<?php

class TableRow
{
	/**
	 * The full <tr> tag that started this row.
	 *
	 * @var string
	 */
	private $openTag;

	// Getter methods and implementing ArrayAccess both made the cells difficult to work with; making $cells public
	// seemed the best way to go.
	/**
	 * The cells in the row.
	 *
	 * @var TableCell[] $cells
	 */
	public $cells = [];

	/**
	 * Creates an instance of a TableRow.
	 *
	 * @param string $openTag The full <tr> tag.
	 *
	 */
	public function __construct(string $openTag)
	{
		$this->openTag = $openTag;
	}

	/**
	 * Gets the opening <tr> tag.
	 *
	 * @return string
	 *
	 */
	public function getOpenTag(): string
	{
		return $this->openTag;
	}
}
