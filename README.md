# Ruby lexeme analyser

[![en](https://img.shields.io/badge/lang-en-red.svg)](README.md)
[![ru](https://img.shields.io/badge/lang-ru-green.svg)](README.ru.md)

## Installation and launch
1. Install PHP 8.1.* <https://www.php.net/>
2. Install Composer 2.4.1 or higher <https://getcomposer.org/>
3. Execute command `composer install`
4. Execute command `php index.php`

### Additional info
- For the program to work correctly, it is necessary that the input files end with an empty line
- You can set some settings in the `config.json` file
  - `realtimeOutputMode` - a parameter that regulates how the data obtained during scanning will be output. Can take values:
    - `0` to disable output
    - `1` to display tokens and their type
    - `2` to display the table header, tokens and their type
  - `displayComments` - a parameter that controls the output of comments during scanning. Can take `bool` values
  - `printResult` - a parameter that controls the output of scan results. Can take `bool` values
