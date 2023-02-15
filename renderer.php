<?php

use dokuwiki\ErrorHandler;
use dokuwiki\plugin\struct\meta\SearchConfig;
use dokuwiki\plugin\struct\meta\StructException;

/**
 * DokuWiki Plugin autolink5 (Renderer Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */
class renderer_plugin_autolink5 extends Doku_Renderer_xhtml
{
    /** @var array[] The glossary terms per page */
    protected $glossary = [];

    /** @var string The compound regex to match all terms */
    protected $regex;

    // region renderer methods

    /**
     * Make this renderer available as alternative default renderer
     *
     * @param string $format
     * @return bool
     */
    public function canRender($format)
    {
        if ($format == 'xhtml') return true;
        return false;
    }

    /** @inheritdoc */
    public function document_start()
    {
        parent::document_start();
        $this->setGlossary($this->loadGlossary());
    }

    /** @inheritDoc */
    public function cdata($text)
    {
        $tokens = $this->findMatchingTokens($text);
        if (!$tokens) {
            parent::cdata($text);
            return;
        }

        $start = 0;
        foreach ($tokens as $token) {
            if ($token['pos'] > $start) {
                parent::cdata(substr($text, $start, $token['pos'] - $start));
            }
            $this->internallink($this->getConf('ns') . ':' . $token['id'], $token['term']);
            $start = $token['pos'] + $token['len'];
        }
        if ($start < strlen($text)) {
            parent::cdata(substr($text, $start));
        }

    }

    // endregion
    // region logic methods

    /**
     * Load the defined glossary terms from struct
     *
     * @return array[] [pageid => [terms, ...], ...]
     */
    public function loadGlossary()
    {
        $schema = $this->getConf('schema');
        $field = $this->getConf('field');
        if (!$schema || !$field) return [];

        try {
            $search = new SearchConfig([
                'schemas' => [[$schema, 'glossary']],
                'cols' => ['%pageid%', $field],
            ]);
            $data = $search->execute();
        } catch (StructException $e) {
            ErrorHandler::logException($e);
            return [];
        }

        $glossary = [];
        foreach ($data as $row) {
            $glossary[$row[0]->getValue()] = $row[1]->getValue();
        }

        return $glossary;
    }

    /**
     * Set the given glossary and rebuild the regex
     *
     * @param array[] $glossary [pageid => [terms, ...], ...]
     */
    public function setGlossary($glossary)
    {
        $this->glossary = $glossary;
        $this->buildPatterns();
    }


    /**
     * initializes the regex to match terms
     */
    public function buildPatterns()
    {
        if (!$this->glossary) {
            $this->regex = null;
            return;
        }

        $patterns = [];
        $num = 0; // term number
        foreach ($this->glossary as $terms) {
            $terms = array_map('preg_quote_cb', $terms);
            $patterns[] = '(?P<p' . ($num++) . '>' . join('|', $terms) . ')';
        }

        $this->regex = '/\b(?:' . implode('|', $patterns) . ')\b/';
    }

    /**
     * Find all matching glossary tokens in the given text
     *
     * @param string $text
     * @return array|false Either an array of tokens or false if no matches were found
     */
    public function findMatchingTokens($text)
    {
        if (!$this->regex) return false;

        if (!preg_match_all($this->regex, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $tokens = [];
        foreach (array_keys($this->glossary) as $num => $id) {
            if(!$this->glossary[$id]) continue; // this page has been linked before

            foreach ($matches["p$num"] as $match) {
                if ($match[0] === '') continue;
                $tokens[] = [
                    'id' => $id,
                    'term' => $match[0],
                    'pos' => $match[1],
                    'len' => strlen($match[0]),
                ];
                $this->glossary[$id] = false; // don't link this page again
                break; // don't link any other term of this page
            }
        }

        // sort by position
        usort($tokens, function ($a, $b) {
            return $a['pos'] - $b['pos'];
        });

        return $tokens;
    }


}

