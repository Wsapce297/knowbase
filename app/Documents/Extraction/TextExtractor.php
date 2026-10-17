<?php

namespace App\Documents\Extraction;

use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Turns the raw bytes of an uploaded file into clean text pages.
 *
 * It takes the file's contents (not a path), so it works with any
 * storage disk and with Storage::fake() in tests.
 */
class TextExtractor
{
    public const SUPPORTED_EXTENSIONS = ['pdf', 'md', 'txt'];

    /**
     * @return list<ExtractedPage> Pages that contain text, in order. Never empty.
     *
     * @throws TextExtractionException
     */
    public function extract(string $contents, string $extension): array
    {
        $extension = strtolower(ltrim($extension, '.'));

        $pages = match ($extension) {
            'pdf' => $this->extractPdf($contents),
            'md', 'txt' => [new ExtractedPage(null, $this->clean($this->toUtf8($contents)))],
            default => throw TextExtractionException::unsupported($extension),
        };

        // Drop blank pages but keep the real page numbers of the others.
        $pages = array_values(array_filter(
            $pages,
            fn (ExtractedPage $page): bool => $page->text !== '',
        ));

        if ($pages === []) {
            throw TextExtractionException::noText();
        }

        return $pages;
    }

    /**
     * @return list<ExtractedPage>
     */
    private function extractPdf(string $contents): array
    {
        $config = new Config;
        $config->setRetainImageContent(false); // we only need text; saves memory

        try {
            $document = (new Parser([], $config))->parseContent($contents);

            $pages = [];
            $number = 1;

            foreach ($document->getPages() as $page) {
                $text = mb_scrub($page->getText(), 'UTF-8');
                $pages[] = new ExtractedPage($number++, $this->joinHyphenatedWords($this->clean($text)));
            }
        } catch (Throwable $e) {
            throw TextExtractionException::unreadablePdf($e);
        }

        return $pages;
    }

    /**
     * Text files saved by older Windows programs are often in
     * Windows-1252 instead of UTF-8. PostgreSQL only accepts valid
     * UTF-8, so convert them.
     */
    private function toUtf8(string $text): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text); // remove a UTF-8 "BOM" marker

        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        return $text;
    }

    /**
     * Normalise whitespace so chunks are tidy and comparable.
     */
    private function clean(string $text): string
    {
        // Windows (\r\n), old Mac (\r) line endings and page breaks (\f) become \n.
        $text = str_replace(["\r\n", "\r", "\f"], "\n", $text);

        // Remove invisible control characters (PostgreSQL rejects \0), keeping \n and \t.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        // Tabs, non-breaking spaces and runs of spaces become one space.
        $text = preg_replace('/\h+/u', ' ', $text);

        // No spaces at the start or end of lines.
        $text = preg_replace('/ *\n */', "\n", $text);

        // At most one blank line between paragraphs.
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * PDFs often break words across lines: "informa-\ntion" -> "information".
     */
    private function joinHyphenatedWords(string $text): string
    {
        return preg_replace('/(\p{L})-\n(\p{Ll})/u', '$1$2', $text);
    }
}
