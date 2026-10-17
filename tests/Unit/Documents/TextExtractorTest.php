<?php

use App\Documents\Extraction\ExtractedPage;
use App\Documents\Extraction\TextExtractionException;
use App\Documents\Extraction\TextExtractor;

function documentFixture(string $name): string
{
    return file_get_contents(__DIR__.'/../../Fixtures/documents/'.$name);
}

it('reads a text file as one page without a page number', function () {
    $pages = (new TextExtractor)->extract("Hello Knowbase.\nSecond line.", 'txt');

    expect($pages)->toHaveCount(1)
        ->and($pages[0])->toBeInstanceOf(ExtractedPage::class)
        ->and($pages[0]->number)->toBeNull()
        ->and($pages[0]->text)->toBe("Hello Knowbase.\nSecond line.");
});

it('reads markdown and ignores the case of the extension', function () {
    $pages = (new TextExtractor)->extract("# Title\n\nSome *markdown* text.", '.MD');

    expect($pages[0]->text)->toBe("# Title\n\nSome *markdown* text.");
});

it('cleans up whitespace, control characters and the BOM', function () {
    $raw = "\xEF\xBB\xBF  First\tline  \r\nSecond\x00 line\xC2\xA0here\r\n\r\n\r\n\r\nNew   paragraph  ";

    $pages = (new TextExtractor)->extract($raw, 'txt');

    expect($pages[0]->text)->toBe("First line\nSecond line here\n\nNew paragraph");
});

it('converts Windows-1252 text to UTF-8', function () {
    $pages = (new TextExtractor)->extract("Caf\xE9 menu", 'txt');

    expect($pages[0]->text)->toBe('Café menu')
        ->and(mb_check_encoding($pages[0]->text, 'UTF-8'))->toBeTrue();
});

it('reads PDF pages with their page numbers and skips blank pages', function () {
    $pages = (new TextExtractor)->extract(documentFixture('sample.pdf'), 'pdf');

    expect($pages)->toHaveCount(2)
        ->and($pages[0]->number)->toBe(1)
        ->and($pages[0]->text)->toContain('Refund policy')
        ->and($pages[0]->text)->toContain('within 30 days')
        ->and($pages[1]->number)->toBe(3)
        ->and($pages[1]->text)->toContain('Shipping times');
});

it('joins words that a PDF hyphenated across lines', function () {
    $pages = (new TextExtractor)->extract(documentFixture('sample.pdf'), 'pdf');

    expect($pages[0]->text)->toContain('contact information so');
});

it('fails when a file has no text', function (string $contents, string $extension) {
    expect(fn () => (new TextExtractor)->extract($contents, $extension))
        ->toThrow(TextExtractionException::class, 'No readable text was found');
})->with([
    'empty text file' => ['', 'txt'],
    'whitespace only' => ["  \n\t\r\n ", 'md'],
    'blank PDF' => [documentFixture('blank.pdf'), 'pdf'],
]);

it('fails with a friendly message for a damaged PDF', function () {
    expect(fn () => (new TextExtractor)->extract('this is not a PDF', 'pdf'))
        ->toThrow(TextExtractionException::class, 'This PDF could not be read');
});

it('fails with a friendly message for an encrypted PDF', function () {
    expect(fn () => (new TextExtractor)->extract(documentFixture('encrypted.pdf'), 'pdf'))
        ->toThrow(TextExtractionException::class, 'password-protected or encrypted');
});

it('rejects unsupported file types', function () {
    expect(fn () => (new TextExtractor)->extract('PK...', 'docx'))
        ->toThrow(TextExtractionException::class, 'Files of type ".docx" are not supported');
});
