<?php

/**
 * Builds a short message for the MegaLinter test.
 *
 * @param string $name The name to put in the message.
 * @return string The finished message.
 */
function makeMessage(string $name): string
{
    return 'Hello ' . $name;
}

echo makeMessage('test');
