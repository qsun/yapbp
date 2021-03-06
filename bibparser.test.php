<?php

require_once('simpletest/autorun.php');
require_once('bibparser.class.php');

class TestOfBibParser extends UnitTestCase 
{
    function testRow() 
    {
        $c = new ParseContext;
        
        $c->parseString(file_get_contents('tests/bib-5'));
        $this->assertEqual($c->currentLine, 3);
    }

    function testReadChar() 
    {
        $context = new ParseContext;
        $context->bibtex = file_get_contents('tests/bib-1');
        
        $this->assertEqual($context->readChar(), array("\n"));
        $this->assertEqual($context->currentLine, 1);
        $this->assertEqual($context->currentCol, 0);
        
        $this->assertEqual($context->readChar(2), array("\n", "\n"));
        $this->assertEqual($context->currentLine, 3);
        $this->assertEqual($context->currentCol, 0);
        
        $this->assertEqual($context->readChar(2), array(' ', ' '));
        $this->assertEqual($context->currentLine, 3);
        $this->assertEqual($context->currentCol, 2);
        $this->assertEqual($context->pointer, 5);

        $readLineResult = $context->readLine(2);
        $this->assertEqual(array(' @misc{ #patashnik-bibtexing,', '       author = "Oren Patashnik",'), $readLineResult);
        $this->assertEqual($context->currentLine, 5);
        $this->assertEqual($context->currentCol, 0);
        
        $context->reset();
        $this->assertEqual($context->pointer, 0);
        $this->assertEqual($context->currentLine, 0);
        $this->assertEqual($context->currentCol, 0);
    }

    function testReadComment() 
    {
        $c = new ParseContext;
        $c->bibtex = file_get_contents('tests/bib-4');
        $c->nop();
        $c->nop();
        $c->nop();
        list($ch) = $c->readChar();
        $this->assertFalse($ch);
    }
    

    function testNop() 
    {
        $context = new ParseContext;
        
        $context->bibtex = '1234567890 
  #abc';
        $context->nop();
        
        $this->assertEqual($context->currentLine, 0);
        $this->assertEqual($context->currentLine, 0);
        
        $r = implode('', $context->readChar(10));
        $this->assertEqual($r, '1234567890');
        
        $context->nop();
        $this->assertEqual(array(), $context->readChar());
        $context->nop();
        $this->assertEqual(array(), $context->readChar());
        $context->nop();
        $this->assertEqual(array(), $context->readChar());
    }

    function testParseEntryName() 
    {
        $c = new ParseContext;
        $c->bibtex = file_get_contents('tests/bib-2');
        $c->nop();
        
        $this->assertEqual('string', $c->parseEntryName());
    }

    function testReadString() 
    {
        $c = new ParseContext;
        $c->bibtex = '{Bib}\\TeX} ';
        $this->assertEqual('{Bib}\\TeX', $c->readString('}'));
    }

    function testParseEntry() 
    {
        $c = new ParseContext;
        $c->bibtex = file_get_contents('tests/bib-2');
        $c->nop();

        $body = $c->parseEntry();
        
        $this->assertEqual($body,
                           array(
                               'name' => 'string',
                               'body' => array(array('btx' => '{\textsc{Bib}\TeX}}'),
                                               array('bty' => '123'))));
        
        
        $c->nop();
        
        $body = $c->parseEntry();
        
        $this->assertEqual($body, array(
                               'name' => 'article',
                               'body' => array('mrx05',
                                               array('author' => 'Mr. X'),
                                               array('title' => 'Something Great'),
                                               array('a' => '{Bib}\\TeX'),
                                               array('b' => '{Bib}\\TeX'),
                                               array('c' => '{Bib}\\TeX'),
                                               array('d' => '{\\textsc{Bib}\\TeX}}ing'),
                                               array('publisher' => 'nobody'),
                                               array('year' => '2005'))));

    }

    function testParseBibTexFile() 
    {
        $content = file_get_contents('tests/bib-2');
        $a = BibParser::parseBibTexString($content);

        $b = BibParser::parseBibTexFile('tests/bib-2');
        $this->assertEqual($a, $b);
        $this->assertEqual($a, array(
                               array(
                                   'name' => 'string',
                                   'body' => array(array('btx' => '{\textsc{Bib}\TeX}}'),
                                                   array('bty' => '123'))),
                               array(
                                   'name' => 'article',
                                   'body' => array('mrx05',
                                                   array('author' => 'Mr. X'),
                                                   array('title' => 'Something Great'),
                                                   array('a' => '{Bib}\\TeX'),
                                                   array('b' => '{Bib}\\TeX'),
                                                   array('c' => '{Bib}\\TeX'),
                                                   array('d' => '{\\textsc{Bib}\\TeX}}ing'),
                                                   array('publisher' => 'nobody'),
                                                   array('year' => '2005')))));

        $c = BibParser::parseBibTexFile('tests/bib-3');
    }

    function testRegressionTests() 
    {
        $this->assertTrue(BibParser::parseBibTexFile('tests/regression/01.bib'));
        $this->assertTrue(BibParser::parseBibTexFile('tests/regression/02.bib'));
        var_dump(BibParser::parseBibTexFile('tests/regression/02.bib'));
    }

}
