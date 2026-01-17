<?php

use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeEmailWithIdStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeFirstNameStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\AnonymizeLastNameStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\CallbackStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\ConditionalStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\DeleteFileStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\HashStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\IpAnonymizeStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\JsonFieldStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\MaskStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\NullStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\RedactedStrategy;
use Bernskiold\LaravelDataScrubber\Strategies\TruncateStrategy;
use Bernskiold\LaravelDataScrubber\Tests\Fixtures\TestModel;
use Illuminate\Support\Facades\Storage;

it('applies null strategy', function () {
    $strategy = new NullStrategy;
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply('some value', $model, 'field');

    expect($result)->toBeNull();
    expect($strategy->label())->toBe('Set to NULL');
    expect($strategy->description())->toBe('Sets the field value to NULL.');
});

it('applies redacted strategy', function () {
    $strategy = new RedactedStrategy;
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply('sensitive data', $model, 'field');

    expect($result)->toBe('[REDACTED]');
    expect($strategy->label())->toBe('Replace with [REDACTED]');
});

it('applies redacted strategy with custom text', function () {
    $strategy = new RedactedStrategy('***HIDDEN***');
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('sensitive data', $model, 'field');

    expect($result)->toBe('***HIDDEN***');
});

it('applies anonymize first name strategy', function () {
    $strategy = new AnonymizeFirstNameStrategy;
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply('John', $model, 'first_name');

    expect($result)->toBe('Deleted');
});

it('applies anonymize first name strategy with custom name', function () {
    $strategy = new AnonymizeFirstNameStrategy('Anonymous');
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('John', $model, 'first_name');

    expect($result)->toBe('Anonymous');
});

it('applies anonymize last name strategy', function () {
    $strategy = new AnonymizeLastNameStrategy;
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply('Doe', $model, 'last_name');

    expect($result)->toBe('User');
});

it('applies anonymize last name strategy with custom name', function () {
    $strategy = new AnonymizeLastNameStrategy('Anonymous');
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('Doe', $model, 'last_name');

    expect($result)->toBe('Anonymous');
});

it('applies anonymize email strategy', function () {
    $strategy = new AnonymizeEmailStrategy;
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply('john@example.com', $model, 'email');

    expect($result)->toBe('anonymized@deleted.local');
});

it('applies anonymize email strategy with custom email', function () {
    $strategy = new AnonymizeEmailStrategy('noreply@example.com');
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('john@example.com', $model, 'email');

    expect($result)->toBe('noreply@example.com');
});

it('applies anonymize email with id strategy', function () {
    $strategy = new AnonymizeEmailWithIdStrategy;
    $model = new TestModel(['id' => 123]);
    $model->id = 123;

    $result = $strategy->apply('john@example.com', $model, 'email');

    expect($result)->toBe('deleted-123@anonymized.local');
});

it('applies anonymize email with id strategy with custom domain and prefix', function () {
    $strategy = new AnonymizeEmailWithIdStrategy('example.com', 'user-');
    $model = new TestModel(['id' => 456]);
    $model->id = 456;

    $result = $strategy->apply('john@example.com', $model, 'email');

    expect($result)->toBe('user-456@example.com');
});

it('applies hash strategy', function () {
    $strategy = new HashStrategy;
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply('sensitive data', $model, 'field');

    expect($result)->toBe(hash('sha256', 'sensitive data'));
});

it('applies hash strategy with custom algorithm', function () {
    $strategy = new HashStrategy('md5');
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('sensitive data', $model, 'field');

    expect($result)->toBe(hash('md5', 'sensitive data'));
});

it('applies hash strategy returns null for null value', function () {
    $strategy = new HashStrategy;
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply(null, $model, 'field');

    expect($result)->toBeNull();
});

it('applies delete file strategy', function () {
    Storage::fake('local');
    Storage::put('test-file.txt', 'content');

    $strategy = new DeleteFileStrategy;
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply('test-file.txt', $model, 'file_path');

    expect($result)->toBeNull();
    Storage::assertMissing('test-file.txt');
});

it('applies delete file strategy with specific disk', function () {
    Storage::fake('public');
    Storage::disk('public')->put('test-file.txt', 'content');

    $strategy = new DeleteFileStrategy('public');
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('test-file.txt', $model, 'file_path');

    expect($result)->toBeNull();
    Storage::disk('public')->assertMissing('test-file.txt');
});

it('applies delete file strategy handles null value', function () {
    $strategy = new DeleteFileStrategy;
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply(null, $model, 'file_path');

    expect($result)->toBeNull();
});

it('applies callback strategy', function () {
    $strategy = new CallbackStrategy(fn ($value, $model, $field) => "custom-{$model->id}-{$field}");
    $model = new TestModel(['id' => 42]);
    $model->id = 42;

    $result = $strategy->apply('original', $model, 'my_field');

    expect($result)->toBe('custom-42-my_field');
});

it('callback strategy receives value model and field', function () {
    $receivedArgs = [];

    $strategy = new CallbackStrategy(function ($value, $model, $field) use (&$receivedArgs) {
        $receivedArgs = compact('value', 'model', 'field');

        return 'result';
    });

    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $strategy->apply('test-value', $model, 'test-field');

    expect($receivedArgs['value'])->toBe('test-value');
    expect($receivedArgs['model'])->toBe($model);
    expect($receivedArgs['field'])->toBe('test-field');
});

// MaskStrategy Tests

it('applies mask strategy', function () {
    $strategy = new MaskStrategy;
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply('1234567890', $model, 'phone');

    expect($result)->toBe('12******90');
    expect($strategy->label())->toBe('Mask middle characters');
});

it('applies mask strategy with custom parameters', function () {
    $strategy = new MaskStrategy(visibleStart: 3, visibleEnd: 4, maskChar: 'X');
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('1234567890', $model, 'phone');

    expect($result)->toBe('123XXX7890');
});

it('applies mask strategy returns null for null value', function () {
    $strategy = new MaskStrategy;
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply(null, $model, 'phone');

    expect($result)->toBeNull();
});

it('applies mask strategy masks entire string when too short', function () {
    $strategy = new MaskStrategy(visibleStart: 2, visibleEnd: 2);
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('abc', $model, 'field');

    expect($result)->toBe('***');
});

it('applies mask strategy handles exact length match', function () {
    $strategy = new MaskStrategy(visibleStart: 2, visibleEnd: 2);
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('abcd', $model, 'field');

    expect($result)->toBe('****');
});

it('applies mask strategy for ssn format', function () {
    $strategy = new MaskStrategy(visibleStart: 0, visibleEnd: 4, maskChar: 'X');
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('123-45-6789', $model, 'ssn');

    expect($result)->toBe('XXXXXXX6789');
});

// TruncateStrategy Tests

it('applies truncate strategy', function () {
    $strategy = new TruncateStrategy;
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply('Jonathan', $model, 'name');

    expect($result)->toBe('Jon***');
    expect($strategy->label())->toBe('Truncate and suffix');
});

it('applies truncate strategy with custom parameters', function () {
    $strategy = new TruncateStrategy(keepChars: 5, suffix: '...');
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('123 Main Street', $model, 'address');

    expect($result)->toBe('123 M...');
});

it('applies truncate strategy returns null for null value', function () {
    $strategy = new TruncateStrategy;
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply(null, $model, 'name');

    expect($result)->toBeNull();
});

it('applies truncate strategy returns suffix for short string', function () {
    $strategy = new TruncateStrategy(keepChars: 5, suffix: '...');
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('abc', $model, 'name');

    expect($result)->toBe('...');
});

it('applies truncate strategy handles exact length match', function () {
    $strategy = new TruncateStrategy(keepChars: 3, suffix: '***');
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('abc', $model, 'name');

    expect($result)->toBe('***');
});

// JsonFieldStrategy Tests

it('applies json field strategy to array', function () {
    $strategy = new JsonFieldStrategy([
        'phone' => new MaskStrategy(2, 2),
        'ssn' => NullStrategy::class,
    ]);
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply([
        'phone' => '1234567890',
        'ssn' => '123-45-6789',
        'name' => 'John Doe',
    ], $model, 'metadata');

    expect($result)->toBe([
        'phone' => '12******90',
        'ssn' => null,
        'name' => 'John Doe',
    ]);
    expect($strategy->label())->toBe('Scrub JSON fields');
});

it('applies json field strategy to json string', function () {
    $strategy = new JsonFieldStrategy([
        'phone' => new MaskStrategy(2, 2),
        'email' => new RedactedStrategy,
    ]);
    $model = new TestModel(['id' => 1]);

    $input = json_encode([
        'phone' => '1234567890',
        'email' => 'john@example.com',
        'name' => 'John Doe',
    ]);

    $result = $strategy->apply($input, $model, 'metadata');

    expect($result)->toBeString();
    $decoded = json_decode($result, true);
    expect($decoded['phone'])->toBe('12******90');
    expect($decoded['email'])->toBe('[REDACTED]');
    expect($decoded['name'])->toBe('John Doe');
});

it('applies json field strategy returns null for null value', function () {
    $strategy = new JsonFieldStrategy([
        'phone' => NullStrategy::class,
    ]);
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply(null, $model, 'metadata');

    expect($result)->toBeNull();
});

it('applies json field strategy returns original for invalid json', function () {
    $strategy = new JsonFieldStrategy([
        'phone' => NullStrategy::class,
    ]);
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('not valid json', $model, 'metadata');

    expect($result)->toBe('not valid json');
});

it('applies json field strategy skips missing keys', function () {
    $strategy = new JsonFieldStrategy([
        'phone' => NullStrategy::class,
        'nonexistent' => RedactedStrategy::class,
    ]);
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply([
        'phone' => '1234567890',
        'name' => 'John',
    ], $model, 'metadata');

    expect($result)->toBe([
        'phone' => null,
        'name' => 'John',
    ]);
});

it('applies json field strategy with class string strategies', function () {
    $strategy = new JsonFieldStrategy([
        'secret' => RedactedStrategy::class,
        'hash' => HashStrategy::class,
    ]);
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply([
        'secret' => 'my-secret',
        'hash' => 'data-to-hash',
    ], $model, 'metadata');

    expect($result['secret'])->toBe('[REDACTED]');
    expect($result['hash'])->toBe(hash('sha256', 'data-to-hash'));
});

// ConditionalStrategy Tests

it('applies conditional strategy when condition is true', function () {
    $strategy = new ConditionalStrategy(
        condition: fn ($value) => strlen($value) > 5,
        thenStrategy: new RedactedStrategy,
    );
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply('long value here', $model, 'field');

    expect($result)->toBe('[REDACTED]');
    expect($strategy->label())->toBe('Conditional scrubbing');
});

it('applies conditional strategy returns original when condition is false without else', function () {
    $strategy = new ConditionalStrategy(
        condition: fn ($value) => strlen($value) > 20,
        thenStrategy: new RedactedStrategy,
    );
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('short', $model, 'field');

    expect($result)->toBe('short');
});

it('applies conditional strategy else strategy when condition is false', function () {
    $strategy = new ConditionalStrategy(
        condition: fn ($value) => strlen($value) > 20,
        thenStrategy: new RedactedStrategy,
        elseStrategy: new NullStrategy,
    );
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('short', $model, 'field');

    expect($result)->toBeNull();
});

it('applies conditional strategy with model-based condition', function () {
    $strategy = new ConditionalStrategy(
        condition: fn ($value, $model) => $model->id > 100,
        thenStrategy: new RedactedStrategy,
        elseStrategy: new MaskStrategy(2, 2),
    );

    $modelLow = new TestModel(['id' => 50]);
    $modelLow->id = 50;
    $modelHigh = new TestModel(['id' => 200]);
    $modelHigh->id = 200;

    $resultLow = $strategy->apply('1234567890', $modelLow, 'phone');
    $resultHigh = $strategy->apply('1234567890', $modelHigh, 'phone');

    expect($resultLow)->toBe('12******90');
    expect($resultHigh)->toBe('[REDACTED]');
});

it('applies conditional strategy with class string strategies', function () {
    $strategy = new ConditionalStrategy(
        condition: fn ($value) => $value !== null,
        thenStrategy: RedactedStrategy::class,
        elseStrategy: NullStrategy::class,
    );
    $model = new TestModel(['id' => 1]);

    $resultWithValue = $strategy->apply('secret', $model, 'field');
    $resultNull = $strategy->apply(null, $model, 'field');

    expect($resultWithValue)->toBe('[REDACTED]');
    expect($resultNull)->toBeNull();
});

it('applies conditional strategy with field-based condition', function () {
    $strategy = new ConditionalStrategy(
        condition: fn ($value, $model, $field) => $field === 'ssn',
        thenStrategy: new MaskStrategy(0, 4, 'X'),
        elseStrategy: new RedactedStrategy,
    );
    $model = new TestModel(['id' => 1]);

    $resultSsn = $strategy->apply('123-45-6789', $model, 'ssn');
    $resultOther = $strategy->apply('secret data', $model, 'notes');

    expect($resultSsn)->toBe('XXXXXXX6789');
    expect($resultOther)->toBe('[REDACTED]');
});

// IpAnonymizeStrategy Tests

it('applies ip anonymize strategy to ipv4 with default mask', function () {
    $strategy = new IpAnonymizeStrategy;
    $model = new TestModel(['id' => 1]);
    $model->id = 1;

    $result = $strategy->apply('192.168.1.100', $model, 'ip_address');

    expect($result)->toBe('192.168.0.0');
    expect($strategy->label())->toBe('Anonymize IP address');
});

it('applies ip anonymize strategy to ipv4 with one octet masked', function () {
    $strategy = new IpAnonymizeStrategy(maskOctets: 1);
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('192.168.1.100', $model, 'ip_address');

    expect($result)->toBe('192.168.1.0');
});

it('applies ip anonymize strategy to ipv4 with three octets masked', function () {
    $strategy = new IpAnonymizeStrategy(maskOctets: 3);
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('192.168.1.100', $model, 'ip_address');

    expect($result)->toBe('192.0.0.0');
});

it('applies ip anonymize strategy to ipv6 with default mask', function () {
    $strategy = new IpAnonymizeStrategy;
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $model, 'ip_address');

    expect($result)->toBe('2001:db8:85a3::8a2e:0:0');
});

it('applies ip anonymize strategy to ipv6 with one group masked', function () {
    $strategy = new IpAnonymizeStrategy(maskOctets: 1);
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $model, 'ip_address');

    expect($result)->toBe('2001:db8:85a3::8a2e:370:0');
});

it('applies ip anonymize strategy returns null for null value', function () {
    $strategy = new IpAnonymizeStrategy;
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply(null, $model, 'ip_address');

    expect($result)->toBeNull();
});

it('applies ip anonymize strategy returns null for empty string', function () {
    $strategy = new IpAnonymizeStrategy;
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('', $model, 'ip_address');

    expect($result)->toBeNull();
});

it('applies ip anonymize strategy returns null for invalid ip', function () {
    $strategy = new IpAnonymizeStrategy;
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('not-an-ip-address', $model, 'ip_address');

    expect($result)->toBeNull();
});

it('applies ip anonymize strategy to compressed ipv6', function () {
    $strategy = new IpAnonymizeStrategy(maskOctets: 1);
    $model = new TestModel(['id' => 1]);

    $result = $strategy->apply('::1', $model, 'ip_address');

    // inet_ntop compresses trailing zeros, so ::0 becomes ::
    expect($result)->toBe('::');
});
