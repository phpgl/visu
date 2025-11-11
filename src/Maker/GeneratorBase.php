<?php

namespace VISU\Maker;

use DirectoryIterator;
use VISU\Maker\Exception\{BadParameterException, CodeGeneratorErrorException};

class GeneratorBase
{
    /**
     * Returns a paramter from the given array and throws an exception if it does not exist
     * 
     * @param array<string, mixed>      $parameters
     * @return mixed
     */
    public function ensureParamter(array &$parameters, string $paramterName)  
    {
        if (!isset($parameters[$paramterName]) || (!$parameters[$paramterName])) {
            throw $this->createMissingParamterException($paramterName);
        }

        return $parameters[$paramterName];
    }

    public function createBadParamterException(string $message) : BadParameterException
    {
        return new BadParameterException($message);
    }

    public function createCodeGeneratorErrorException(string $message) : CodeGeneratorErrorException
    {
        return new CodeGeneratorErrorException($message);
    }


    public function createMissingParamterException(string $paramterName) : BadParameterException
    {
        return $this->createBadParamterException(sprintf("The paramter '%s' is requried for " . __CLASS__, $paramterName));
    }

    /**
     * Returns contents of the projects composer json file
     * 
     * @return array<mixed>
     */
    public function getComposerJsonData() : array
    {
        $path = VISU_PATH_ROOT . '/composer.json';

        if (!file_exists($path)) {
            throw new CodeGeneratorErrorException(sprintf('The composer json file could not be found, trying to open "%s"', $path));
        }

        if (!$data = file_get_contents($path)) {
            throw new CodeGeneratorErrorException(sprintf('The composer json file could not be loaded, trying to open "%s"', $path));
        }
        
        if (!$data = json_decode($data, true)) {
            throw new CodeGeneratorErrorException(sprintf('The composer json file could not be decoded at "%s"', $path));
        }

        return $data;
    }

    /**
     * Trims the given namespace, replaces forward slashes with backwards ones etc..
     */
    public function fixNamespaceString(string $namespace) : string
    {
        return trim(trim(str_replace('/', "\\", $namespace)), "\\");
    }

    /**
     * Returns an absolute path for the given namespace.
     * The path is determined by matching the namespace against PSR-4 definitions
     * in the composer json file.
     */
    public function getPathForNamespace(string $namespace) : string
    {
        $namespace = $this->fixNamespaceString($namespace); 

        $composerData = $this->getComposerJsonData();
        
        if (empty($composerData['autoload']['psr-4'])) {
            throw $this->createCodeGeneratorErrorException("We conly support PSR-4 autoloaded code generation!");
        }

        $availableNamespaces = $composerData['autoload']['psr-4'];
        $targetNamespacePrefix = null;
        $targetNamespacePath = null;

        foreach ($availableNamespaces as $namespacePrefix => $path) {
            // check if the prefix matches
            if (substr($namespace, 0, strlen($namespacePrefix)) === $namespacePrefix) {
                $targetNamespacePrefix = $namespacePrefix;
                $targetNamespacePath = $path;
                break;
            }
        }

        if (is_null($targetNamespacePrefix) || is_null($targetNamespacePath)) {
            throw $this->createCodeGeneratorErrorException(sprintf("Could not match the given namespace '%s' to a autoloaded one...", $namespace));
        }

        return VISU_PATH_ROOT . '/' . $targetNamespacePath . str_replace("\\", '/', substr($namespace, strlen($targetNamespacePrefix))) . '.php';
    }

    /**
     * Indent / pad the given string
     */
    public function indentString(string $input, int $indentLength = 4) : string
    {
        $lines = explode(PHP_EOL, $input);
        $p = str_repeat(' ', $indentLength);
        foreach ($lines as &$line) {
            $line = $p . $line;
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * Splits namespace and class name from a full namespace
     * 
     * @param string            $namespace
     * @return array{string, string}
     */
    public function splitNamespaceAndClass(string $namespace) : array
    {
        if (!$p = strrpos($namespace, "\\")) {
            throw $this->createCodeGeneratorErrorException(sprintf("Could not split namespace '%s' into two parts.", $namespace));
        }

        return [
            substr($namespace, 0, $p),
            substr($namespace, $p + 1)
        ];
    }

    /**
     * Updates the give code change to be appended to a ctn file.
     * The filepath of the CodeChange is used as the target to determine offsets.
     */
    public function appendToCtn(CodeChange $change) : void
    {
        if (file_exists($change->filepath)) {
            $ctnContents = file_get_contents($change->filepath) ?: '';
            $change->fullOverride = false;

            $ctnSize = mb_strlen($ctnContents);
            $lastChar = $ctnContents[$ctnSize - 1];

            if ($lastChar !== PHP_EOL) {
                $change->code = PHP_EOL . $change->code;
            }

            // always prepend additional line break
            $change->code = PHP_EOL . $change->code;

            $change->offsetStart = $ctnSize;
            $change->offsetEnd = $ctnSize;
        } else {
            $change->fullOverride = true;
        }
    }

    /**
     * Returns a string containing the given message as a multiline comment
     */
    public function generateMultilineComment(string $comment) : string
    {
        $buffer = "/**" . PHP_EOL;
        $lines = explode(PHP_EOL, $comment);
        foreach($lines as &$line) {
            $line = ' * ' . $line;
        }
        $buffer .= implode(PHP_EOL, $lines);
        $buffer .= PHP_EOL . ' */' . PHP_EOL;
        
        return $buffer;
    }

    /**
     * Generate class buffers
     * 
     * @param string                                            $namespace The namespace including the class name
     * @param string                                            $content The content buffer of the class
     * @param array{
     *      extends?: string, 
     *      implements?: array<string>,
     *      structType?: string,
     *      use?: array<string>,
     *      comment?: string
     * } $options
     */
    public function generateClassBuffer(string $namespace, string $content, array $options = []) : string 
    {
        // default struct type is class
        if (!isset($options['structType'])) {
            $options['structType'] = 'class';
        }

        $buffer = '<?php' . PHP_EOL . PHP_EOL;
        list($classNamespace, $className) = $this->splitNamespaceAndClass($namespace);

        // define the namespace
        $buffer .= 'namespace ' . $classNamespace . ';';

        // define imports
        $namespaceImports = $options['use'] ?? [];
        if (isset($options['extends'])) {
            $namespaceImports[] = $options['extends'];
        }

        if (isset($options['implements'])) {
            foreach ($options['implements'] as $implementedInterface) {
                $namespaceImports[] = $implementedInterface;
            }
        }

        if ($namespaceImports) {
            $buffer .= PHP_EOL;
            sort($namespaceImports);
            foreach ($namespaceImports as $import) {
                $buffer .= PHP_EOL . 'use ' . $import . ';';
            }
        }

        // define class
        $buffer .= PHP_EOL . PHP_EOL;
        if (isset($options['comment']) && $options['comment']) {
            $buffer .= $this->generateMultilineComment($options['comment']);
        }
        $buffer .= $options['structType'] . " " . $className;

        // class extends
        if (isset($options['extends'])) {
            list($extendsPath, $extendsName) = $this->splitNamespaceAndClass($options['extends']);
            $buffer .= ' extends ' . $extendsName;
        }

        // class implements
        if (isset($options['implements']) && $options['implements']) {
            $buffer .= ' implements ';
            $implementedInterfaceNames = [];
            foreach ($options['implements'] as $implementedInterface) {
                list($interfacePath, $interfaceName) = $this->splitNamespaceAndClass($implementedInterface);
                $implementedInterfaceNames[] = $interfaceName;
            }

            $buffer .= implode(', ', $implementedInterfaceNames);
        }

        // define struct contents
        $buffer .= PHP_EOL . '{' . PHP_EOL;
        $buffer .= $this->indentString(trim($content));
        $buffer .= PHP_EOL . '}' . PHP_EOL;

        return $buffer;
    }

    /**
     * Returns an array of class names in the given namespace that are abstract definitions
     * 
     * @return array<string>
     */
    public function findAbstractClassesInNamespace(string $namespace) : array
    {
        $namespacePath = substr($this->getPathForNamespace($namespace), 0, -4);

        $classes = [];

        $di = new DirectoryIterator($namespacePath);
        foreach ($di as $item) {
            if ($item->isDir()) continue;

            $fileCode = file_get_contents($item->getPathname()) ?: '';

            if (preg_match("/abstract class (.*) extends (.*)Action/", $fileCode)) {
                $classes[] = $namespace . "\\" . substr($item->getFilename(), 0, -4);
            }
        }

        return $classes;
    }

    /**
     * Returns bool if the given regex matches in the given file
     * If the file does not exist false is returned aswell.
     */
    public function pregMatchInFile(string $filepath, string $regex) : bool 
    {
        if (!file_exists($filepath)) {
            return false;
        }

        $fileCode = file_get_contents($filepath) ?: '';

        return (bool) preg_match($regex, $fileCode);
    }

    public function convertNamespaceToServiceName(string $namespace, string $prefix = '') : string
    {
        $prefixArray = [];
        if ($prefix) {
            $prefixArray[] = $prefix;
        }

        $serviceName = array_merge($prefixArray, explode("\\", $namespace));
        $serviceName = array_map(function($v) {
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $v) ?: '');
        }, $serviceName);

        return '@' . implode('.', $serviceName);
    }

    public function convertCamelCaseToUnderscore(string $string) : string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string) ?: '');
    }

    public function convertUndescoreToCamelCase(string $string) : string
    {
        return lcfirst(implode('', array_map('ucfirst', explode('_', $string))));
    }

    /**
     * Everything below is taken from the following source:
     * 
     * // original source: http://kuwamoto.org/2007/12/17/improved-pluralizing-in-php-actionscript-and-ror/
     * 
     * The MIT License (MIT)
     *
     * Copyright (c) 2015
     * 
     * Permission is hereby granted, free of charge, to any person obtaining a copy
     * of this software and associated documentation files (the "Software"), to deal
     * in the Software without restriction, including without limitation the rights
     * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
     * copies of the Software, and to permit persons to whom the Software is
     * furnished to do so, subject to the following conditions:
     * 
     * The above copyright notice and this permission notice shall be included in
     * all copies or substantial portions of the Software.
     * 
     * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
     * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
     * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
     * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
     * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
     * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
     * THE SOFTWARE.
     * 
     * 
     * ------------------------------------------------------------------------------------------------------------
     */
    /**
     * @var array<string, string>
     */
    private static array $infelctorPlural = [
        '/(quiz)$/i'               => "$1zes",
        '/^(ox)$/i'                => "$1en",
        '/([m|l])ouse$/i'          => "$1ice",
        '/(matr|vert|ind)ix|ex$/i' => "$1ices",
        '/(x|ch|ss|sh)$/i'         => "$1es",
        '/([^aeiouy]|qu)y$/i'      => "$1ies",
        '/(hive)$/i'               => "$1s",
        '/(?:([^f])fe|([lr])f)$/i' => "$1$2ves",
        '/(shea|lea|loa|thie)f$/i' => "$1ves",
        '/sis$/i'                  => "ses",
        '/([ti])um$/i'             => "$1a",
        '/(tomat|potat|ech|her|vet)o$/i' => "$1oes",
        '/(bu)s$/i'                => "$1ses",
        '/(alias)$/i'              => "$1es",
        '/(octop)us$/i'            => "$1i",
        '/(ax|test)is$/i'          => "$1es",
        '/(us)$/i'                 => "$1es",
        '/s$/i'                    => "s",
        '/$/'                      => "s"
    ];

    /**
     * @var array<string, string>
     */
    private static array $infelctorSingular = [
        '/(quiz)zes$/i'             => "$1",
        '/(matr)ices$/i'            => "$1ix",
        '/(vert|ind)ices$/i'        => "$1ex",
        '/^(ox)en$/i'               => "$1",
        '/(alias)es$/i'             => "$1",
        '/(octop|vir)i$/i'          => "$1us",
        '/(cris|ax|test)es$/i'      => "$1is",
        '/(shoe)s$/i'               => "$1",
        '/(o)es$/i'                 => "$1",
        '/(bus)es$/i'               => "$1",
        '/([m|l])ice$/i'            => "$1ouse",
        '/(x|ch|ss|sh)es$/i'        => "$1",
        '/(m)ovies$/i'              => "$1ovie",
        '/(s)eries$/i'              => "$1eries",
        '/([^aeiouy]|qu)ies$/i'     => "$1y",
        '/([lr])ves$/i'             => "$1f",
        '/(tive)s$/i'               => "$1",
        '/(hive)s$/i'               => "$1",
        '/(li|wi|kni)ves$/i'        => "$1fe",
        '/(shea|loa|lea|thie)ves$/i' => "$1f",
        '/(^analy)ses$/i'           => "$1sis",
        '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i'  => "$1$2sis",
        '/([ti])a$/i'               => "$1um",
        '/(n)ews$/i'                => "$1ews",
        '/(h|bl)ouses$/i'           => "$1ouse",
        '/(corpse)s$/i'             => "$1",
        '/(us)es$/i'                => "$1",
        '/s$/i'                     => ""
    ];

    /**
     * @var array<string, string>
     */
    private static array $infelctorIrregular = [
        'move'   => 'moves',
        'foot'   => 'feet',
        'goose'  => 'geese',
        'sex'    => 'sexes',
        'child'  => 'children',
        'man'    => 'men',
        'tooth'  => 'teeth',
        'person' => 'people',
        'valve'  => 'valves'
    ];

    /**
     * @var array<string>
     */
    private static array $infelctorUncountable = [
        'sheep',
        'fish',
        'deer',
        'series',
        'species',
        'money',
        'rice',
        'information',
        'equipment'
    ];

    public function pluralizeString(string $string) : string
    {
        // save some time in the case that singular and plural are the same
        if (in_array(strtolower($string), self::$infelctorUncountable))
            return $string;


        // check for irregular singular forms
        foreach (self::$infelctorIrregular as $pattern => $result) {
            $pattern = '/' . $pattern . '$/i';

            if (preg_match($pattern, $string))
                return preg_replace($pattern, $result, $string) ?: '';
        }

        // check for matches using regular expressions
        foreach (self::$infelctorPlural as $pattern => $result) {
            if (preg_match($pattern, $string))
                return preg_replace($pattern, $result, $string) ?: '';
        }

        return $string;
    }

    public function singularizeString(string $string) : string
    {
        // save some time in the case that singular and plural are the same
        if (in_array(strtolower($string), self::$infelctorUncountable))
            return $string;

        // check for irregular plural forms
        foreach (self::$infelctorIrregular as $result => $pattern) {
            $pattern = '/' . $pattern . '$/i';

            if (preg_match($pattern, $string))
                return preg_replace($pattern, $result, $string) ?: '';
        }

        // check for matches using regular expressions
        foreach (self::$infelctorSingular as $pattern => $result) {
            if (preg_match($pattern, $string))
                return preg_replace($pattern, $result, $string) ?: '';
        }

        return $string;
    }
}
