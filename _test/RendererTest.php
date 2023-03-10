<?php

namespace dokuwiki\plugin\structautolink\test;

use DokuWikiTest;

/**
 * FIXME tests for the structautolink plugin
 *
 * @group plugin_structautolink
 * @group plugins
 */
class RendererTest extends DokuWikiTest
{
    public function testFindMatchingTokens()
    {
        $R = new \renderer_plugin_structautolink();
        $R->setGlossary([
            'ml' => ['ML', 'Maschinelles Lernen', 'Machine Learning'],
            'ki' => ['KI', 'Künstliche Intelligenz', 'AI', 'Artificial Intelligence'],
            'dl' => ['DL', 'Deep Learning'],
            'nlp' => ['NLP', 'Natural Language Processing'],
        ]);

        $text = 'Was wir über Künstliche Intelligenz wissen, kann uns nur Machine Learning beantworten. dl ist egal.';
        $result = $R->findMatchingTokens($text);

        $this->assertEquals(
            [
                [
                    'id' => 'ki',
                    'term' => 'Künstliche Intelligenz',
                    'pos' => 14,
                    'len' => 23,
                ],
                [
                    'id' => 'ml',
                    'term' => 'Machine Learning',
                    'pos' => 59,
                    'len' => 16,
                ],
            ],
            $result
        );

        // check that positions above are actually correct
        $this->assertEquals($result[0]['term'], substr($text, $result[0]['pos'], $result[0]['len']));
        $this->assertEquals($result[1]['term'], substr($text, $result[1]['pos'], $result[1]['len']));
    }

    public function testCdata()
    {
        $R = new \renderer_plugin_structautolink();
        $R->setGlossary([
            'ml' => ['ML', 'Maschinelles Lernen', 'Machine Learning'],
            'ki' => ['KI', 'Künstliche Intelligenz', 'AI', 'Artificial Intelligence'],
            'dl' => ['DL', 'Deep Learning'],
            'nlp' => ['NLP', 'Natural Language Processing'],
        ]);

        $R->cdata('Was wir über Künstliche Intelligenz wissen, kann uns nur Machine Learning beantworten.');
        $R->cdata('Künstliche Intelligenz ist KI'); // should not be linked again
        $R->cdata('dl ist egal.'); // lowercase != uppercase
        $result = $R->doc;

        $this->assertStringStartsWith('Was wir über <a href', $result);
        $this->assertStringEndsWith('dl ist egal.', $result);

        if(class_exists('DOMWrap\Document')) {
            $pq = (new \DOMWrap\Document())->html($result);
        } else {
            // deprecated
            $pq = \phpQuery::newDocumentHTML($result);
        }

        $this->assertEquals(2, $pq->find('a')->count());
        $this->assertEquals('Künstliche Intelligenz', $pq->find('a')->eq(0)->text());
        $this->assertEquals('Machine Learning', $pq->find('a')->eq(1)->text());
    }
}
