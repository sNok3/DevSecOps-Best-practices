<?php

namespace App\Support;

/**
 * Allow-list regular expressions for every free-text input. Length is always checked separately
 * with `max:` so a regex never has to do it (and cannot be tricked into catastrophic backtracking).
 */
class InputRules
{
    /** Letters (any language), combining marks, space, apostrophe, hyphen, dot. 2-60 chars. */
    public const NAME = '/^[\p{L}\p{M}][\p{L}\p{M} \'.-]{1,59}$/u';

    /** Note title: printable text, no control characters and no angle brackets. */
    public const TITLE = '/^[^\p{C}<>]{1,120}$/u';

    /** Multi-line text: anything printable plus tab/CR/LF, no other control characters (e.g. NUL, ESC). */
    public const MULTILINE = '/^(?:[^\p{C}]|[\t\r\n])*$/u';

    /** Uploaded file name: word chars, space, dot, hyphen, brackets; must end in an allowed extension. */
    public const FILE_NAME = '/^[\w\- ().]{1,100}\.(pdf|png|jpe?g|txt)$/iu';
}
