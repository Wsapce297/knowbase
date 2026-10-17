<?php

namespace App\Documents\Extraction;

use RuntimeException;
use Throwable;

/**
 * Thrown when a document's text can't be read.
 *
 * The messages are written for end users, because the
 * ProcessDocument job (Part 7) will save them in documents.error
 * and the documents page will show them.
 */
class TextExtractionException extends RuntimeException
{
    public static function unsupported(string $extension): self
    {
        return new self("Files of type \".{$extension}\" are not supported. Upload a PDF, Markdown or text file.");
    }

    public static function unreadablePdf(Throwable $previous): self
    {
        if (str_contains(strtolower($previous->getMessage()), 'secured')) {
            return new self('This PDF is password-protected or encrypted, so its text can\'t be read. Upload an unprotected copy.', previous: $previous);
        }

        return new self('This PDF could not be read. It may be damaged or use an unusual format.', previous: $previous);
    }

    public static function noText(): self
    {
        return new self('No readable text was found. Scanned PDFs (pictures of pages) are not supported yet.');
    }
}
