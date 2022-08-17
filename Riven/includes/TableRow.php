<?php

class TableRow
{
    /**
     * The full <tr> tag that started this row.
     *
     * @var string
     */
    private $openTag = '';

    // Getter methods and implementing ArrayAccess both made the cells difficult to work with; making $cells public
    // seemed the best way to go.
    /**
     * The cells in the row.
     *
     * @var TableCell[]
     */
    public $cells = [];

    public function __construct($openTag)
    {
        $this->openTag = $openTag;
    }

    public function getOpenTag()
    {
        return $this->openTag;
    }
}
