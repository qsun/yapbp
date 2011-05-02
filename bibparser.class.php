<?php

class ParseException extends Exception 
{
    var $parseContext;
    
    public function __construct(&$parseContext, $message) 
    {
        $message = $message . " Row: " . $parseContext->currentLine . " Col: " . $parseContext->currentCol;
        
        parent::__construct($message);
    }
    
    public function __toString() 
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message} - Parse Context: " . $parseContext;
    }
}

class ParseContext 
{
    var $currentLine, $currentCol, $entries, $strings;

    var $pointer;
    
    var $bibtex;

    var $bypassChars = array("\n", "\t", " ");
    var $result;

    var $debug = false;

    function ParseContext() 
    {
        $this->reset();
    }
    

    /* reset counters */
    function reset() 
    {
        $this->result = array();
        $this->pointer = 0;
        $this->currentLine = 0;
        $this->currentCol = 0;
        $this->entries = array();
        $this->strings = array();
    }
    

    /* read one (or more) char from string */
    function readChar($num = 1) 
    {
        $result = array();
        
        while ($num > 0) {
            if ($this->pointer > strlen($this->bibtex)) {
                return $result;
            }

            $char = $this->bibtex[$this->pointer];
            if ($char === "\n") {
                $this->currentLine++;
                $this->currentCol = 0;
            } else {
                $this->currentCol++;
            }
            
            $result[] = $char;
            
            $this->pointer++;
            $num--;
        }

        return $result;
    }

    function unreadChar($num = 1) 
    {
        $this->currentCol--;
        if ($this->bibtex[$this->pointer - 1] === "\n") {
            $this->currentLine--;
            $this->currentCol = 0;
        }
        
        $this->pointer = $this->pointer - $num;
    }

    function readLine($num = 1)
    {
        $result = array();
        
        for ($i == 0; $i < $num; $i++) {
            $buffer = '';
            
            while (list($c) = $this->readChar()) {
                if ($c != "\n") {
                    $buffer = $buffer . $c;
                } else {
                    $result[] = $buffer;
                    break;
                }
            }
        }

        return $result;
    }

    /* ignore useless space, new lines, comment */
    function nop($strict = false) 
    {
        while (list($char) = $this->readChar()) {
            
            if (in_array($char, $this->bypassChars)) {
            } else if ($strict != true && ($char == '#' || $char == '%')) {
                $this->readLine();
            } else {
                $this->unreadChar();
                break;
            }
        }
    }

    function parsePlainString($allowed = 'abcdefghijklmnopqrstuvwxyz01234567890-_+') {
        $buffer = '';
        while (list($c) = $this->readChar()) {
            if (false === stripos($allowed, $c)) {
                $this->unreadChar();
                
                return $buffer;
            } else {
                $buffer = $buffer . $c;
                continue;
            }
        }
    }

    function parseEntryName() 
    {
        list($char) = $this->readChar();
        if ($char === '') {
            return false;
        }
        
        $this->expectEqual($char, '@', '@ missing');

        return $this->parsePlainString();
    }

    function readTagName() 
    {
        list($c) = $this->readChar();
        if ($c == '"') {
            $tagName = $this->readString('"');
        } else if ($c == "'") {
            $tagName = $this->readString("'");
        } else  if ($c == '{') {
            $tagName = $this->readString('}');
        } else {
            $this->unreadChar();
            $tagName = $this->parsePlainString();
        }

        return strtolower($tagName);
    }

    /* keep reading, until find the char, return the buffer */
    function readString($endChar)
    {
        $prev = false;
        $buffer = '';

        $nestedLevel = 0;
        
        while (1) {
            list($c) = $this->readChar();

            if ($c == $endChar && $prev != '\\') {
                return $buffer;
            } else {
                if ($c == '{' && $endChar == '}') {
                    $str = $this->readString('}');
                    $buffer = $buffer . '{' . $str . '}';
                } else {
                    $buffer = $buffer . $c;
                }
            }

            $prev = $c;
        }
    }
    

    function readTagValue() 
    {
        $buffer = '';
        
        do {
            $this->nop();
            
            list($c) = $this->readChar();
            if ($c == '{') {
                $s = $this->readString('}');
            } else if ($c == '"') {
                $s = $this->readString('"');
            } else if ($c == "'") {
                $s = $this->readString("'");
            } else if (stripos(' abcdefghijklmnopqrstuvwxyz01234567890', $c)) {
                $s = $c . $this->parsePlainString();
                
                if ($this->strings[$s]) {
                    $s = $this->strings[$s];
                }
            }

            $this->nop(true);

            list($c) = $this->readChar();
            if ($c != '#') {
                $this->unreadChar();
            }
            
            $buffer = $buffer . $s;
        } while ($c == '#');
        
        return $buffer;
    }

    function readTag() 
    {
        $this->nop();

        list ($c) = $this->readChar();
        if ($c != ',') {
            $this->unreadChar();
        }

        if ($c == '}') {
            return false;
        }
        
        $this->nop();
        $name = $this->readTagName();
        
        $this->nop();

        if (!$name) {
            return false;
        }

        list($c) = $this->readChar();
        
        if ($c == '=') {
            $value = $this->readTagValue();
        } else if ($c == '}') {
            $this->unreadChar();
            $value = false;
        } else if ($c != '{' && $c != ',') {
            $this->exception('Invalid tag: ' . $name. " ");
        } else {
            $this->unreadChar();
            $value = false;
        }
        
        if ($value === false) {
            return $name;
        } else {
            return array($name => $value);
        }
    }

    function readTags() 
    {
        $tags = array();
        
        while ($tag = $this->readTag()) {
            $tags[] = $tag;
        }
        
        return $tags;
    }

    function parseEntryBody() 
    {
        list($c) = $this->readChar();
        
        $this->expectEqual($c, '{', 'Invalid entry start');

        $tags = $this->readTags();
        
        $this->nop();

        list($c) = $this->readChar();
        
        $this->expectEqual($c, '}', 'Invalid entry end');

        return $tags;
    }
    
    function parseEntry() 
    {
        $this->nop();
        $name = $this->parseEntryName();
        if (!$name) {
            return false;
        }
        
        $this->nop();
        $body = $this->parseEntryBody();

        if ($name == 'string') {
            foreach ($body as $string) {
                foreach($string as $abbr => $full) {
                    $this->strings[$abbr] = $full;
                }
            }
        }

        return array('name' => $name, 'body' => $body);
    }
    
    function expectEqual($strA, $strB, $message = 'Failed to parse bibtex') 
    {
        if ($strA != $strB) {
            $this->exception($message);
        }
    }

    function exception($msg) 
    {
        if ($this->debug) {
            throw new ParseException($this, $msg);
        } else {
        }
    }
    
    function parseString($string) 
    {
        $this->reset();
        $this->bibtex = $string;
        
        while ($entry = $this->parseEntry()) {
            $this->result[] = $entry;
        }
    }

    function getResult() 
    {
        return $this->result;
    }
}


class BibParser 
{
    static function parseBibTexString($string) 
    {
        $context = new ParseContext;
        $context->parseString($string);

        return $context->getResult();
    }

    static function parseBibTexFile($filename) 
    {
        $content = file_get_contents($filename);
        
        return BibParser::parseBibTexString($content);
    }
    
}
