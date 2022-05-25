[stackoverflow.com](https://bit.ly/3GjhXmP)

edited Mar 26, 2016 at 22:00

Readline for Windows
===

### May Work, Worth Trying

This might be a little bit late, but here's the solution that solved this problem for me: In style of C#'s Console, I wrote a little class that can do a `readLine()` as well as a `writeLine($str)`:

```php
class Console {
    const READLINE_MAX_LENGTH = 0xFFFF;
    const WRITELINE_NEWLINE = "\n";

    private static /*Resource*/ $stdin;
    private static /*Resource*/ $stdout;

    public static function /*void*/ close () {
        fclose(self::$stdin);
        fclose(self::$stdout);
    }

    public static function /*void*/ open () {
        self::$stdin = fopen('php://stdin', 'r');
        self::$stdout = fopen('php://stdout', 'w');
    }

    public static function /*string*/ readLine () {
        return stream_get_line(self::$stdin, self::READLINE_MAX_LENGTH, "\r\n");
    }

    public static function /*void*/ writeLine (/*string*/ $str) {
        fwrite(self::$stdout, $str);
        fwrite(self::$stdout, self::WRITELINE_NEWLINE);
    }
}
```
Example usage:

```php
Console::open();
echo "Input something: ";

$str = Console::readLine();
if (is_string($str))
    Console::writeLine($str);
else
    echo "ERROR";

Console::close();
```
EDIT: This method obviously only works, if the parent process doesn't change `STDOUT` or `STDIN`.