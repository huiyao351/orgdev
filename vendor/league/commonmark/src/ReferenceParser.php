<?php

/*
 * This file is part of the league/commonmark package.
 *
 * (c) Colin O'Dell <colinodell@gmail.com>
 *
 * Original code based on the CommonMark JS reference parser (https://bitly.com/commonmark-js)
 *  - (c) John MacFarlane
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\CommonMark;

use League\CommonMark\Reference\Reference;
use League\CommonMark\Reference\ReferenceMap;
use League\CommonMark\Util\LinkParserHelper;

class ReferenceParser
{
    /**
     * @var ReferenceMap
     */
    protected $referenceMap;

    public function __construct(ReferenceMap $referenceMap)
    {
        $this->referenceMap = $referenceMap;
    }

    /**
     * Attempt to parse a link reference, modifying the refmap.
     *
     * @param Cursor $cursor
     *
     * @return bool
     */
    public function parse(Cursor $cursor)
    {
        if ($cursor->isAtEnd()) {
            return false;
        }

        $initialState = $cursor->saveState();

        $matchChars = LinkParserHelper::parseLinkLabel($cursor);
        if ($matchChars === 0) {
            $cursor->restoreState($initialState);

            return false;
        }

        // We need to trim the opening and closing brackets from the previously-matched text
        $label = substr($cursor->getPreviousText(), 1, -1);

        if (preg_match('/[^\s]/', $label) === 0) {
            $cursor->restoreState($initialState);

            return false;
        }

        if ($cursor->getCharacter() !== ':') {
            $cursor->restoreState($initialState);

            return false;
        }

        // Advance past the colon
        $cursor->advance();

        // Link URL
        $cursor->advanceToFirstNonSpace();

        $destination = LinkParserHelper::parseLinkDestination($cursor);
        if (empty($destination)) {
            $cursor->restoreState($initialState);

            return false;
        }

        $previousState = $cursor->saveState();
        $cursor->advanceToFirstNonSpace();

        $title = LinkParserHelper::parseLinkTitle($cursor);
        if ($title === null) {
            $title = '';
            $cursor->restoreState($previousState);
        }

        // Make sure we're at line end:
        $atLineEnd = true;
        if ($cursor->match('/^ *(?:\n|$)/') === null) {
            if ($title === '') {
                $atLineEnd = false;
            } else {
                // The potential title we found is not at the line end,
                // but it could still be a legal link reference if we
                // discard the title
                $title = '';
                // rewind before spaces
                $cursor->restoreState($previousState);
                // and instead check if the link URL is at the line end
                $atLineEnd = $cursor->match('/^ *(?:\n|$)/') !== null;
            }
        }

        if (!$atLineEnd) {
            $cursor->restoreState($initialState);

            return false;
        }

        if (!$this->referenceMap->contains($label)) {
            $reference = new Reference($label, $destination, $title);
            $this->referenceMap->addReference($reference);
        }

        return true;
    }
}
