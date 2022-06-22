<?php
/*
namespace MediaWiki\Extension\MetaTemplate;
*/

/**
 * [Description Riven]
 */
class Riven
{
    const AV_RECURSIVE = 'riven-recursive';
    const AV_TOP = 'riven-top';

    const NA_EXPLODE = 'riven-explode';
    const NA_MODE = 'riven-mode';
    const NA_PROTROWS = 'riven-protectrows';
    const NA_SEED = 'riven-seed';
    const NA_SEPARATOR = 'riven-separator';

    const PF_ARG = 'riven-arg'; // From DynamicFunctions
    const PF_IFEXISTX = 'riven-ifexistx';
    const PF_INCLUDE = 'riven-include';
    const PF_PICKFROM = 'riven-pickfrom';
    const PF_RAND = 'riven-rand'; // From DynamicFunctions
    const PF_SPLITARGS = 'riven-splitargs';
    const PF_TRIMLINKS = 'riven-trimlinks';

    const TG_CLEANSPACE = 'riven-cleanspace';
    const TG_CLEANTABLE = 'riven-cleantable';

    const TRACKING_EXPLODEARGS = 'riven-tracking-explodeargs';

    const VR_SKINNAME = 'riven-skinname'; // From DynamicFunctions

    // From DynamicFunctions
    /**
     * doArg
     *
     * @param Parser $parser
     * @param string $name
     * @param string $default
     *
     * @return string
     */
    public static function doArg(Parser $parser, PPFrame $frame, array $args)
    {
        $values = ParserHelper::expandArray($frame, $args);
        $request = RequestContext::getMain()->getRequest();
        return $request->getVal($values[0], isset($values[1]) ? $values[1] : '');
    }

    public static function doCleanSpace($text, array $args, Parser $parser, PPFrame $frame)
    {
        $output = trim($text);
        $output = preg_replace('#<!--.*?-->#', '', $output);
        $mode = ParserHelper::getMagicValue(self::NA_MODE, $args, 'none');
        // show($mode);
        $modeWord = ParserHelper::findMagicID($mode);
        show($modeWord);
        // show($modeWord);
        switch ($modeWord) {
            case self::AV_RECURSIVE:
                $output = self::cleanSpacePP($output, $parser, $frame, true);
                break;
            case self::AV_TOP:
                $output = self::cleanSpacePP($output, $parser, $frame, false);
                break;
            default:
                $output = self::cleanSpaceOriginal($output);
        }

        if (ParserHelper::checkDebugMagic($parser, $args)) {
            return ['<pre>' . htmlspecialchars($output) . '</pre>', 'markerType' => 'nowiki'];
        }

        if (!$parser->getOptions()->getIsPreview() && $parser->getTitle()->getNamespace() == NS_TEMPLATE) {
            // categories and trails are stripped on ''any'' template page, not just when directly calling the template
            // (but only in non-preview mode)
            // save categories before processing
            $precats = $parser->getOutput()->getCategories();
            $output = $parser->recursiveTagParse($output, $frame);
            // reset categories to the pre-processing list to remove any new categories
            $parser->getOutput()->setCategoryLinks($precats);
            return $output;
        }

        return $parser->recursiveTagParse($output, $frame);
    }

    // unset links that are removed (remove from wantedlinks)?
    // fix issues with <small> tags ... if cell is empty except for paired tags, consider it empty
    public static function doCleanTable($text, $args = array(), Parser $parser, PPFrame $frame)
    {
        $input = $parser->recursiveTagParse($text, $frame);
        $isPreview = $parser->getOptions()->getIsPreview();

        // This ensures that tables are not cleaned if being displayed directly on the Template page.
        // Previewing will process cleantable normally.
        if (
            $parser->getTitle()->getNamespace() == NS_TEMPLATE &&
            $frame->depth == 0 &&
            $isPreview
        ) {
            return $input;
        }

        $input = trim($input);
        $offset = 0;
        $output = '';
        $lastVal = null;
        $protectRows = intval(ParserHelper::getMagicValue(self::NA_PROTROWS, $args, 1));
        do {
            $lastVal = self::parseTable($parser, $input, $offset, $protectRows);
            $output .= $lastVal;
        } while ($lastVal);

        $after = substr($input, $offset);
        $output .= $after;

        if (strlen($output > 0) && ParserHelper::checkDebugMagic($parser, $args)) {
            $output = $parser->recursiveTagParseFully($output);
            return ['<pre>' . htmlspecialchars($output) . '</pre>', 'markerType' => 'nowiki'];
        }

        return $output;
    }

    /**
     * doIfExistX
     *
     * @param Parser $parser
     * @param PPFrame $frame
     * @param array $args
     *
     * @return string
     */
    public static function doIfExistX(Parser $parser, PPFrame $frame, array $args)
    {
        list($magicArgs, $values) = ParserHelper::getMagicArgs(
            $frame,
            $args,
            ParserHelper::NA_IF,
            ParserHelper::NA_IFNOT
        );
        $titleText = trim($frame->expand(ParserHelper::arrayGet($values, 0, '')));
        $title = Title::newFromText($titleText);
        if ($title && ParserHelper::checkIfs($magicArgs)) {
            $then = ParserHelper::arrayGet($values, 1);
            $else = ParserHelper::arrayGet($values, 2);
            $retval = $else;
            $parser->getFunctionLang()->findVariantLink($titleText, $title, true);
            switch ($title->getNamespace()) {
                case NS_MEDIA:
                    if ($parser->incrementExpensiveFunctionCount()) {
                        $file = wfFindFile($title);
                        if ($file) {
                            $parser->getOutput()->addImage(
                                $file->getName(),
                                $file->getTimestamp(),
                                $file->getSha1()
                            );

                            $retval = $file->exists() ? $then : $else;
                        }
                    }

                    break;
                case NS_SPECIAL:
                    $retval = SpecialPageFactory::exists($title->getDBkey()) ? $then : $else;
                    break;
                default:
                    if (!$title->isExternal()) {
                        $pdbk = $title->getPrefixedDBkey();
                        $lc = LinkCache::singleton();
                        if (
                            $lc->getGoodLinkID($pdbk) !== 0 ||
                            (!$lc->isBadLink($pdbk) &&
                                $parser->incrementExpensiveFunctionCount() &&
                                $title->exists())
                        ) {
                            $retval = $then;
                        }
                    }

                    break;
            }
        }

        return trim($frame->expand($retval));
    }

    /**
     * doInclude
     *
     * @param Parser $parser
     * @param PPFrame $frame
     * @param array $args
     *
     * @return string
     */
    public static function doInclude(Parser $parser, PPFrame $frame, array $args)
    {
        list($magicArgs, $values) = ParserHelper::getMagicArgs(
            $frame,
            $args,
            ParserHelper::NA_DEBUG,
            ParserHelper::NA_IF,
            ParserHelper::NA_IFNOT
        );

        if (count($values) > 0 && ParserHelper::checkIfs($magicArgs)) {
            $nodes = '';
            // show('Values: ', $values);
            foreach ($values as $pageName) {
                $pageName = $frame->expand($pageName);
                // show($pageName);
                $t = Title::newFromText($pageName, NS_TEMPLATE);
                if ($t && $t->exists()) {
                    // show('Exists!');
                    $nodes .= '{{' . $pageName . '}}';
                }
            }
            // show('Nodes: ', $nodes);

            return [$nodes, 'noparse' => ParserHelper::checkDebug($parser, $magicArgs)];
        }
    }

    // IMP: List is still randomized, even if all items will be returned.
    /**
     * doPickFrom
     *
     * @param Parser $parser
     * @param PPFrame $frame
     * @param array $args
     *
     * @return string
     */
    public static function doPickFrom(Parser $parser, PPFrame $frame, array $args)
    {
        list($magicArgs, $values) = ParserHelper::getMagicArgs(
            $frame,
            $args,
            ParserHelper::NA_IF,
            ParserHelper::NA_IFNOT,
            self::NA_SEED,
            self::NA_SEPARATOR
        );
        $npick = intval(array_shift($values));
        if ($npick <= 0 || count($values) == 0 || !ParserHelper::checkIfs($magicArgs)) {
            return '';
        }

        $separator = $frame->expand(ParserHelper::arrayGet($magicArgs, self::NA_SEPARATOR, "\n"));
        if (strlen($separator) > 1) {
            $separator = stripcslashes($separator);
            $first = $separator[0];
            if (in_array($first, ['\'', '`', '"']) && $first === substr($separator, -1, 1)) {
                $separator = substr($separator, 1, -1);
                $parser->addTrackingCategory('riven-pickfromquotes-category');
            }
        }

        if (isset($magicArgs[self::NA_SEED])) {
            // Shuffle uses the basic randomizer, so we seed with srand if requested.
            // As of PHP 7.1.0, shuffle uses the mt randomizer, but srand is then aliased to mt_srand, so no urgent need to change it.
            srand(($frame->expand($magicArgs[self::NA_SEED])));
        }

        shuffle($values); // randomize list
        if ($npick < count($values)) {
            array_splice($values, $npick); // cut off unwanted items
        }

        $retval = [];
        foreach ($values as $value) {
            $retval[] = trim($frame->expand($value));
        }

        return implode($separator, $retval);
    }

    // IMP: Now allows a seed
    // IMP: Defaults to d6.
    /**
     * doRand
     *
     * @param Parser $parser
     * @param PPFrame $frame
     * @param array $args
     *
     * @return int
     */
    public static function doRand(Parser $parser, PPFrame $frame, array $args)
    {
        list($magicArgs, $values) = ParserHelper::getMagicArgs(
            $frame,
            $args,
            self::NA_SEED
        );
        $values = ParserHelper::expandArray($frame, $values);
        if (isset($magicArgs[self::NA_SEED])) {
            mt_srand(($frame->expand($magicArgs[self::NA_SEED])));
        }

        $low = ParserHelper::arrayGet($values, 0);
        if (count($values) == 1) {
            $high = $low;
            $low = 1;
        } else {
            $high = intval(ParserHelper::arrayGet($values, 1, $low));
        }

        if ($low != $high) {
            $frame->setVolatile();
        }

        return ($low > $high) ? mt_rand($high, $low) : mt_rand($low, $high);
    }

    // From DynamicFunctions
    /**
     * doSkinName
     *
     * @param Parser $parser
     *
     * @return string
     */
    public static function doSkinName()
    {
        return RequestContext::getMain()->getSkin()->getSkinName();
    }

    // IMP: Adds nowiki option to view actual template calls for debugging. Only works in preview mode by default; set to 'always' to have it display that way when saved.
    // IMP: All anonymous and named arguments in the list are added to each call.
    /**
     * doSplitArgs
     *
     * @param Parser $parser
     * @param PPFrame $frame
     * @param array $args
     *
     * @return string
     */
    public static function doSplitArgs(Parser $parser, PPFrame $frame, array $args)
    {
        list($magicArgs, $values) = ParserHelper::getMagicArgs(
            $frame,
            $args,
            ParserHelper::NA_DEBUG,
            ParserHelper::NA_IF,
            ParserHelper::NA_IFNOT,
            self::NA_EXPLODE,
            self::NA_SEPARATOR
        );

        if (!ParserHelper::checkIfs($magicArgs)) {
            return '';
        }

        if (isset($values[1])) {
            // Figure out what we're dealing with and populate appropriately.
            $checkFormat = $frame->expand($values[1]);
            if (!is_numeric($checkFormat) && count($values) > 3) {
                // Old #explodeargs; can be deleted once all are converted.
                $values = ParserHelper::expandArray($frame, $values);
                $nargs = $frame->expand($values[3]);
                $templateName = $frame->expand($values[2]);
                $separator = $checkFormat;
                $values = explode($separator, $frame->expand($values[0]));
                $parser->addTrackingCategory(self::TRACKING_EXPLODEARGS);
            } else {
                $templateName = $frame->expand($values[0]);
                $nargs = $checkFormat;
                $values = array_slice($values, 2);
                if (isset($magicArgs[self::NA_EXPLODE])) {
                    $separator = ParserHelper::arrayGet($magicArgs, self::NA_SEPARATOR, ',');
                    $explode = $frame->expand($magicArgs[self::NA_EXPLODE]);
                    $values = array_merge(explode($separator, $explode), $values);
                }

                if (empty($values)) {
                    $values = $frame->getNumberedArguments();
                    foreach ($frame->getNamedArguments() as $key => $value) {
                        $numKey = intval($key);
                        if ($numKey > 0) {
                            $values[$numKey] = $value;
                        }
                    }
                }
            }
        }

        $nargs = intval($nargs);
        if ($nargs == 0) {
            $nargs = count($values);
        }

        list($named, $values) = self::splitNamedArgs($frame, $values);
        if (count($values) > 0) {
            $templates = '';
            for ($index = 0; $index < count($values); $index += $nargs) {
                $templates .= '{{' . $templateName;
                for ($paramNum = 0; $paramNum < $nargs; $paramNum++) {
                    $var = ParserHelper::arrayGet($values, $index + $paramNum, '');
                    $templates .= "|$var";
                }

                foreach ($named as $name => $value) {
                    $templates .= "|$name=$value";
                }

                $templates .= '}}';
            }


            return [$templates, 'noparse' => ParserHelper::checkDebug($parser, $magicArgs)];
        }
    }

    // IMP: Ignores Category and Image links unless they're forced links; better parsing of edge cases (nested links, nowiki, etc.).
    /**
     * doTrimLinks
     *
     * @param Parser $parser
     * @param PPFrame $frame
     * @param array $args
     *
     * @return string
     */
    public static function doTrimLinks(Parser $parser, PPFrame $frame, array $args)
    {
        if (!isset($args[0])) {
            return '';
        }

        $preprocessor = new Preprocessor_Hash($parser);
        $flag = $frame->depth ? Parser::PTD_FOR_INCLUSION : 0;
        $rootNode = $preprocessor->preprocessToObj($args[0], $flag);
        self::trimLinksParseNode($parser, $rootNode);
        return $frame->expand($rootNode);
    }

    public static function init()
    {
        ParserHelper::cacheMagicWords([
            self::AV_RECURSIVE,
            self::AV_TOP,
            self::NA_EXPLODE,
            self::NA_MODE,
            self::NA_PROTROWS,
            self::NA_SEED,
            self::NA_SEPARATOR,
        ]);
    }

    private static function cleanRows($input, $protectRows = 1)
    {
        // show("Clean Rows In:\n", $input);
        $map = self::buildMap($input);
        // show($map);
        $sectionHasContent = false;
        for ($rowNum = count($map) - 1; $rowNum >= $protectRows; $rowNum--) {
            $row = $map[$rowNum];
            $rowHasContent = false;
            $allHeaders = true;
            $spans = [];

            foreach ($row as $cell) {
                if ($cell instanceof TableCell) {
                    $content = trim(preg_replace('#\{\{\{[^\}]*\}\}\}#', '', html_entity_decode($cell->getContent())));
                    $rowHasContent |= !$cell->isHeader() && strlen($content) > 0;
                    $allHeaders &= $cell->isHeader();
                    if ($cell->getParent()) {
                        $spans[] = $cell->getParent();
                    }
                }
            }

            // show('Row: ', $rowNum, "\n", $rowHasContent, "\n", $row);
            $sectionHasContent |= $rowHasContent;
            if ($allHeaders) {
                if ($sectionHasContent) {
                    $sectionHasContent = false;
                } else {
                    unset($map[$rowNum]);
                }
            } elseif (!$rowHasContent) {
                /** @var TableCell $cell */
                foreach ($spans as $cell) {
                    $cell->decrementRowspan();
                    // show('RowCount: ', $cell->getRowspan());
                }

                unset($map[$rowNum]);
            }
        }

        // show($map);
        return self::mapToTable($map);
    }

    private static function cleanSpaceNode(PPNode $node, $recurse)
    {
        $prevNode = null;
        while ($node) {
            if ($node instanceof PPNode_Hash_Text) {
                // Remove space at beginning and end of text, and around anything that looks like a valid tag.
                $trimmed = preg_replace('#\s*(</?[0-9A-Za-z]+[^>]*>)\s*#', '$1', trim($node->value));
                if (self::isLink($prevNode)) {
                    // Also remove space after a link's closing ]]. Only do this once - anything else is not a link closer.
                    $trimmed = preg_replace('#]]\s+#', ']]', $trimmed, 1);
                }

                $node->value = $trimmed;
            } elseif ($recurse && $node->getFirstChild()) {
                self::cleanSpaceNode($node->getFirstChild(), true);
            }

            $prevNode = $node;
            $node = $node->getNextSibling();
        }
    }

    private static function cleanSpaceOriginal($text)
    {
        // return preg_replace('/([\]\}\>])\s+([\<\{\[])/s', '$1$2', $text);
    }

    private static function cleanSpacePP($text, Parser $parser, PPFrame $frame, $recurse)
    {
        $preprocessor = new Preprocessor_Hash($parser);
        $flag = $frame->depth ? Parser::PTD_FOR_INCLUSION : 0;
        $rootNode = $preprocessor->preprocessToObj($text, $flag);
        self::cleanSpaceNode($rootNode->getFirstChild(), $recurse);

        return $frame->expand($rootNode, PPFrame::RECOVER_ORIG);
    }

    /**
     * buildMap
     *
     * @param mixed $input
     *
     * @return array
     */
    private static function buildMap($input)
    {
        $map = [];
        $rowNum = 0;
        preg_match_all('#(<tr[^>]*>)(.*?)</tr\s*>#is', $input, $rawRows, PREG_SET_ORDER);
        foreach ($rawRows as $rawRow) {
            $map[$rowNum]['open'] = $rawRow[1];
            $cellNum = 0;
            preg_match_all('#<(?<name>t[dh])\s*(?<attribs>[^>]*)>(?<content>.*?)</\1\s*>#s', $rawRow[0], $rawCells, PREG_SET_ORDER);
            foreach ($rawCells as $rawCell) {
                $cell = new TableCell($rawCell);
                while (isset($map[$rowNum][$cellNum])) {
                    $cellNum++;
                }

                $map[$rowNum][$cellNum] = $cell;
                $rowspan = $cell->getRowspan();
                $colspan = $cell->getColspan();
                if ($rowspan > 1 || $colspan > 1) {
                    $spanCell = new TableCell($cell);
                    for ($r = 0; $r < $rowspan; $r++) {
                        for ($c = 0; $c < $colspan; $c++) {
                            if ($r != 0 || $c != 0) {
                                $map[$rowNum + $r][$cellNum + $c] = $spanCell;
                            }
                        }
                    }
                }
            }

            $rowNum++;
        }

        return $map;
    }

    private static function isLink(PPNode $node = null)
    {
        return $node instanceof PPNode_Hash_Text && $node->value == '[[';
    }

    private static function mapToTable($map)
    {
        $output = '';
        foreach ($map as $row) {
            $output .= $row['open'] . "\n";
            foreach ($row as $name => $cell) {
                if ($name !== 'open') {
                    // Conditional is to avoid unwanted blank lines in output.
                    $html = $cell->toHtml();
                    if ($html) {
                        $output .= $html . "\n";
                    }
                }
            }

            $output .= "</tr>\n";
        }

        return $output;
    }

    private static function parseTable(Parser $parser, $input, &$offset, $protectRows, $open = null)
    {
        // show("Parse Table In:\n", substr($input, $offset));
        $output = '';
        $before = null;
        while (preg_match('#</?table[^>]*?>\s*#i', $input, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $match = $matches[0];
            if (is_null($before) && is_null($open)) {
                $before = ($match[1] > $offset)
                    ? substr($input, $offset, $match[1] - $offset)
                    : '';
                $offset = $match[1];
            }

            $output .= substr($input, $offset, $match[1] - $offset);
            $offset = $match[1] + strlen($match[0]);
            if ($match[0][1] == '/') {
                $output = self::cleanRows($output, $protectRows);
                // show("Clean Rows Out:\n", $output);
                break;
            } else {
                $output .= self::parseTable($parser, $input, $offset, $protectRows, $match[0]);
                // show("Parse Table Out:\n", $output);
            }
        }

        if (!is_null($open) && strlen($output) > 0) {
            $output = $open . $output . '</table>';
            $output = $parser->insertStripItem($output);
        }

        // show('Before: ', $before);
        // show('Output: ', $output);
        return $before . $output;
    }

    /**
     * splitNamedArgs
     *
     * @param PPFrame $frame
     * @param array|null $args
     *
     * @return array
     */
    private static function splitNamedArgs(PPFrame $frame, array $args = null)
    {
        $named = [];
        $unnamed = [];
        if (!is_null($args)) {
            foreach ($args as $arg) {
                list($name, $value) = ParserHelper::getKeyValue($frame, $arg);
                if (is_null($name)) {
                    $unnamed[] = $value;
                } else {
                    $named[(string)$name] = $value;
                }
            }
        }

        return [$named, $unnamed];
    }

    /**
     * trimLinksParseNode
     *
     * @param Parser $parser
     * @param PPNode $node
     *
     * @return void
     */
    private static function trimLinksParseNode(Parser $parser, PPNode $node)
    {
        if ($node instanceof PPNode_Hash_Text && $node == '[[') {
            $content = $node->getNextSibling();
            if ($content instanceof PPNode_Hash_Text) {
                $contentText = (string)$content;
                $close = strpos($contentText, ']]'); // Should always be the end of the content text.
                if ($close == strlen($contentText) - 2) {
                    $link = substr($contentText, 0, $close);
                    $split = explode('|', $link, 2);
                    $titleText = trim($split[0]);
                    $leadingColon = $titleText[0] == ':';
                    $title = Title::newFromText($titleText);
                    $ns = $title ? $title->getNamespace() : 0;
                    if ($leadingColon) {
                        $titleText = substr($titleText, 1);
                        if ($ns === NS_MEDIA) {
                            $leadingColon = false;
                        }
                    }

                    if ($leadingColon || !in_array($ns, [NS_CATEGORY, NS_FILE, NS_MEDIA, NS_SPECIAL])) {
                        $node->value = '';
                        $content->value = substr($contentText, $close + 2);
                        if (isset($split[1])) {
                            // If display text was provided, preserve formatting but put nowiki pairs at each end to break any accidental formatting that results.
                            $node->value = $parser->insertStripItem('<nowiki/>') . $split[1] . $parser->insertStripItem('<nowiki/>');
                        } else {
                            // For title-only links, formatting should not be applied at all, so just surround the entire thing with nowiki tags.
                            $text = $title ? $title->getPrefixedText() : $titleText;
                            $node->value = $parser->insertStripItem("<nowiki>$text</nowiki>");
                        }
                    }
                }
            }
        } elseif ($node instanceof PPNode_Hash_Tree) {
            $child = $node->getFirstChild();
            while ($child) {
                self::trimLinksParseNode($parser, $child);
                $child = $child->getNextSibling();
            }
        }
    }
}
