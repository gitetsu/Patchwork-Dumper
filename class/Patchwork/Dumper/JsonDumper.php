<?php // vi: set fenc=utf-8 ts=4 sw=4 et:
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 */

namespace Patchwork\Dumper;

/**
 * JsonDumper implements the JSON convention to dump any PHP variable with high accuracy.
 *
 * See https://github.com/nicolas-grekas/Patchwork-Doc/blob/master/Dumping-PHP-Data-en.md
 */
class JsonDumper extends Dumper
{
    public

    $maxString = 100000;


    protected function dumpRef($is_soft, $ref_counter = null, &$ref_value = null, $ref_type = null)
    {
        if (parent::dumpRef($is_soft, $ref_counter, $ref_value, $ref_type)) return true;

        $is_soft = $is_soft ? 'r' : 'R';
        $this->line .= "\"{$is_soft}`{$this->counter}:{$ref_counter}\"";

        return false;
    }

    protected function dumpScalar($a)
    {
        switch (true)
        {
        case null === $a: $this->line .= 'null'; break;
        case true === $a: $this->line .= 'true'; break;
        case false === $a: $this->line .= 'false'; break;
        case INF === $a: $this->line .= '"n`INF"'; break;
        case -INF === $a: $this->line .= '"n`-INF"'; break;
        case is_nan($a): $this->line .= '"n`NAN"'; break;
        case $a > 9007199254740992 && is_int($a): $a = '"n`' . $a . '"'; // JavaScript max integer is 2^53
        default: $this->line .= (string) $a; break;
        }
    }

    protected function dumpString($a, $is_key)
    {
        if ($is_key)
        {
            $this->line .= ',';
            $is_key = $this->lastHash === $this->counter;

            if ('__cutBy' === $a)
            {
                if (! $is_key) $this->dumpLine(0);
            }
            else
            {
                $is_key = $is_key && ! isset($this->depthLimited[$this->counter]);
                $this->dumpLine(-$is_key);
            }

            $is_key = ': ';
        }
        else $is_key = '';

        if ('' === $a) return $this->line .= '""' . $is_key;

        if (! preg_match('//u', $a)) $a = 'b`' . utf8_encode($a);
        else if (false !== strpos($a, '`')) $a = 'u`' . $a;

        if (0 < $this->maxString && $this->maxString < $len = iconv_strlen($a, 'UTF-8') - 1)
            $a = $len . ('`' !== substr($a, 1, 1) ? 'u`' : '') . iconv_substr($a, 0, $this->maxString + 1, 'UTF-8');

        static $map = array(
            array(
                  '\\', '"', '</',
                  "\x00",  "\x01",  "\x02",  "\x03",  "\x04",  "\x05",  "\x06",  "\x07",
                  "\x08",  "\x09",  "\x0A",  "\x0B",  "\x0C",  "\x0D",  "\x0E",  "\x0F",
                  "\x10",  "\x11",  "\x12",  "\x13",  "\x14",  "\x15",  "\x16",  "\x17",
                  "\x18",  "\x19",  "\x1A",  "\x1B",  "\x1C",  "\x1D",  "\x1E",  "\x1F",
            ),
            array(
                '\\\\', '\\"', '<\\/',
                '\u0000','\u0001','\u0002','\u0003','\u0004','\u0005','\u0006','\u0007',
                '\b'    ,'\t'    ,'\n'    ,'\u000B','\f'    ,'\r'    ,'\u000E','\u000F',
                '\u0010','\u0011','\u0012','\u0013','\u0014','\u0015','\u0016','\u0017',
                '\u0018','\u0019','\u001A','\u001B','\u001C','\u001D','\u001E','\u001F',
            ),
        );

        $this->line .= '"' . str_replace($map[0], $map[1], $a) . '"' . $is_key;
    }

    protected function walkHash($type, &$a, $len)
    {
        if ('array:0' === $type) $this->line .= '[]';
        else
        {
            $this->line .= '{"_":';
            $this->dumpString($this->counter . ':' . $type, false);

            $startCounter = $this->counter;

            if ($type = parent::walkHash($type, $a, $len))
            {
                ++$this->depth;
                $this->dumpString('__refs', true);
                foreach ($type as $k => $v) $type[$k] = '"' . $k . '":[' . implode(',', $v) . ']';
                $this->line .= '{' . implode(',', $type) . '}';
                --$this->depth;
            }

            if ($this->counter !== $startCounter) $this->dumpLine(1);

            $this->line .= '}';
        }
    }
}
