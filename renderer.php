<?php

/**
 * DokuWiki Plugin autolink5 (Renderer Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */
class renderer_plugin_autolink5 extends Doku_Renderer_xhtml
{
    /** @var array[] The glossary terms per page */
    protected $glossary = [
        'ml' => ['ML', 'Maschinelles Lernen', 'Machine Learning'],
        'ki' => ['KI', 'Künstliche Intelligenz', 'AI', 'Artificial Intelligence'],
        'dl' => ['DL', 'Deep Learning'],
        'nlp' => ['NLP', 'Natural Language Processing'],
    ];

    /** @var array The same as $glossary but with a named regex pattern as value */
    protected $patterns = [];

    /** @var string The compound regex to match all terms */
    protected $regex;

    /**
     * Constructor
     *
     * initializes the regex to match terms
     */
    public function __construct()
    {

        foreach ($this->glossary as $id => $terms) {
            // FIXME sort terms by length
            $terms = array_map('preg_quote_cb', $terms);

            $this->patterns[$id] = '(?P<' . $id . '>' . join('|', $terms) . ')';
        }

        $this->regex = '/\b(?:' . implode('|', array_values($this->patterns)) . ')\b/';
    }

    /**
     * Find all matching glossary tokens in the given text
     *
     * @param string $text
     * @return array|false Either an array of tokens or false if no matches were found
     */
    public function findMatchingTokens($text)
    {
        if (!preg_match_all($this->regex, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $tokens = [];
        foreach (array_keys($this->glossary) as $id) {
            if (!isset($matches[$id])) continue;
            foreach ($matches[$id] as $match) {
                if ($match[0] === '') continue;
                $tokens[] = [
                    'id' => $id,
                    'term' => $match[0],
                    'pos' => $match[1],
                    'len' => strlen($match[0]),
                ];
                break;
            }
        }

        // sort by position
        usort($tokens, function ($a, $b) {
            return $a['pos'] - $b['pos'];
        });

        return $tokens;
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


}

