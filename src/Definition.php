<?php

namespace Tuchsoft\MoodleChecklist;



/*
 *
 * Help me write a few techincal paragaph that desribe the use of this Congifuration parser



It use a dot separted code (xx.xx.xx) that can be provided in two way:

hirarchly in a multidimensianl array or "flat" using the dot syntax

the two syntax can be mixed toghether

The definition is parsed top to button, glob value starting with (*) are evalueted last (for each level)

Each node can contain both chldren and info




 */


use Exception;

class Definition
{
    public static array $definition = [];
    public static array $override = [];
    private const CHAR_PRIORITY = [
            '?' => 2,
            '*' => 3,
        ];

    private const GLOB_REGEX = '/[\*\?\[\]\!]/';
    private const GLOB_KEY = '_glob_';
    private const REQUIRED_PROPERTIES = ['msg', 'ref', 'help', 'severity', 'desc', 'active'];


    /**
     * @param string $definitionFile
     * @param string|array $override
     */
    public function __construct(string $definitionFile, string|array $override)
    {
        $override = is_string($override) ? $this->loadFromFiles($override) : $override;
        $this->parseDefinition($this->loadFromFiles($definitionFile), $override);
    }



    /**
     * @param string $files
     * @return array
     */
    private function loadFromFiles(...$files): array {
        $definitions = [];
        foreach ($files as $file) {
            if (!is_string($file)) {
                throw new Exception('Input path must be string');
            }

            if (!is_file($file)) {
                throw new Exception("Definition file '{$file}' does not exist");
            }

            $jsonContent = @file_get_contents($file);
            if (!$jsonContent) {
                throw new Exception("Could not read definition file '{$file}'");
            }

            $data = json_decode($jsonContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Error decoding definition file '{$file}': " . json_last_error_msg());
            }
            $definitions = array_merge_recursive($definitions, $data);
        }
        return $definitions;
    }

    /**
     * Parsa la definizione ISSUE in una struttura ad albero normalizzata.
     * Gestisce percorsi a punti e array annidati, inclusi i pattern glob.
     */
    /**
     * @param array $definitions
     * @param array $override
     */
    private function parseDefinition(array $definitions, array $override): void
    {
        $output = [];
        $this->processArrayRecursively($definitions, $output);
        static::$definition = $output;

        $output = [];
        $this->processArrayRecursively($override, $output);
        static::$override = $output;
    }

    /**
     * Metodo ricorsivo per processare le chiavi e i valori.
     *
     * @param array $input L'array o sotto-array da processare.
     * @param array $output Il riferimento all'array di output dove inserire i dati.
     * @param string $prefix Il prefisso del percorso corrente per la ricorsione (utile per debug).
     */
    private function processArrayRecursively(array $input, array &$output, string $prefix = ''): void
    {
        foreach ($input as $key => $value) {
            $glob = false;


            if (!is_string($key)) {
                throw new Exception("All key in the definition must be strings ($key)");
            }

            if (empty($key)) {
                throw new Exception('Empty key are not allowed in the deinftion');
            }

            if ($value === []) {
                continue;
            }

            // Se la chiave è una stringa e contiene punti, la processiamo come un percorso flattened.
            // Questo copre sia i casi di primo livello che quelli annidati (es. se 'prop.subprop' fosse un valore di un array)
            if (str_contains($key, '.')) {
                $parts = explode('.', $key);
                $temp = &$output;

                foreach ($parts as $part) {
                    // Controlla se la parte corrente contiene caratteri glob
                    /** @noinspection DuplicatedCode */
                    if (preg_match(self::GLOB_REGEX, $part)) {
                        $glob = true;
                        if (!isset($temp[self::GLOB_KEY])) {
                            $temp[self::GLOB_KEY] = [];
                        }
                        $temp = &$temp[self::GLOB_KEY];
                    }
                    if (!isset($temp[$part])) {
                        $temp[$part] = [];
                    }
                    $temp = &$temp[$part];

                }

                if (is_array($value)) {
                    $temp = array_replace_recursive($temp, $value);
                } else {
                    $temp = $value;
                }

                if ($glob) {
                    uksort($temp, [$this, 'compareGlobKeys']);
                }

            } elseif (is_array($value)) {
                // Se il valore è un array, dobbiamo scendere ricorsivamente.
                // Prima, gestiamo la chiave corrente (che potrebbe essere un glob o un nome normale)
                $target_node = &$output;

                // Se la chiave stessa è un glob (es. '*' come chiave diretta)
                /** @noinspection DuplicatedCode */
                if (preg_match(self::GLOB_REGEX, $key)) {
                    $glob = true;
                    if (!isset($target_node[self::GLOB_KEY])) {
                        $target_node[self::GLOB_KEY] = [];
                    }
                    $target_node = &$target_node[self::GLOB_KEY];
                }

                // Assicurati che il nodo per la chiave esista prima di passarne il riferimento alla ricorsione
                if (!isset($target_node[$key])) {
                    $target_node[$key] = [];
                }

                if ($glob) {
                    uksort($target_node, [$this, 'compareGlobKeys']);
                }

                // Chiamata ricorsiva per processare il sotto-array
                $this->processArrayRecursively($value, $target_node[$key], ($prefix ? $prefix . '.' : '') . $key);

            } else {
                // Caso di chiave normale (non a punto) e valore non array
                // e la chiave non è un glob di primo livello (già gestito prima).
                // Assegna direttamente il valore al nodo corrente.
                $output[$key] = $value;
            }
        }
    }


    /**
     * Metodo privato per il confronto delle chiavi glob.
     * Segue l'ordine di priorità: Carattere normale < (Assenza di carattere) < '?' < '*'
     *
     * @param string $a Prima stringa da confrontare.
     * @param string $b Seconda stringa da confrontare.
     * @return int 0 se uguali, un numero negativo se $a viene prima di $b, un numero positivo se $a viene dopo di $b.
     */
    private function compareGlobKeys(string $a, string $b): int {
        $priority_a = '';
        $priority_b = '';
        for ($i = 0; $i < max(strlen($a),  strlen($b)); $i++) {
            $char_a = $a[$i] ?? null;
            $char_b = $b[$i] ?? null;
            $priority_a .= $char_a === null ? 1 : (static::CHAR_PRIORITY[$char_a] ?? 0);
            $priority_b .= $char_b === null ? 1 : (static::CHAR_PRIORITY[$char_b] ?? 0);
        }
        return intval($priority_a) - intval($priority_b);
    }


    // --- Nuova funzione get() ricorsiva ---
    /**
     * Recupera la definizione per un dato codice, navigando la struttura in modo ricorsivo e gestendo i glob.
     *
     * @param string $code Il codice da cercare (es. 'readme.title' o 'path.subfolder.my_file').
     * @return array La definizione trovata, o un array con campi vuoti se non trovata o incompleta.
     */
    public function get(string $code): array
    {
        $parts = explode('.', $code);

        $info =  $this->_get($code, static::$override, $parts);
        $info =  $this->_get($code, static::$definition, $parts, $info);
        
        return $info;
    }

    /**
     * Metodo helper ricorsivo per la navigazione della definizione.
     *
     * @param array $currentNode Il nodo corrente della definizione in cui cercare.
     * @param array $remainingParts Le parti rimanenti del codice da cercare.
     * @param array $info The partial result that accumulate the info found, MUST BE empty the first call
     * @return array|null Il nodo trovato, o null se non trovato.
     */
    private function _get(string $code, array $currentNode, array $remainingParts, array $info = []): ?array
    {

        $info = $this->fillMissingProperties($info, $currentNode);

        // Se non ci sono più parti da cercare, abbiamo raggiunto la destinazione.
        if (empty($remainingParts)) {
            return $info;
        }

        /** @var string $currentPart The current analyzed part of the input code (split by '.') */
        $currentPart = array_shift($remainingParts);

        // Tentativo 1: Match diretto della parte nel nodo corrente
        if (isset($currentNode[$currentPart])) {
            //Found the exact Match
            /** @var array $subNode The found part that match the code, it could contain info and/or other children*/
            $subNode = $currentNode[$currentPart];


            if (is_array($subNode)) {
                return $this->_get($code, $subNode, $remainingParts, $info);
            } else {
                return $info;
            }
        }

        // Tentativo 2: Cerca sotto _glob_
        if (isset($currentNode[self::GLOB_KEY]) && is_array($currentNode[self::GLOB_KEY])) {
            $globNode = $currentNode[self::GLOB_KEY];

            foreach ($globNode as $globPattern => $subNode) {
                $tmpGlob = $info['globSum'] ? $info['globSum'].'.'.$globPattern : $globPattern;
                $tmpCode = $info['codeSum'] ? $info['codeSum'].'.'.$currentPart : $currentPart;
                if (fnmatch($tmpGlob, $tmpCode)) {
                    $info['globSum'] = $tmpGlob;
                    $info['codeSum'] = $tmpCode;
                    // Trovato un match glob
                    if (is_array($subNode)) {
                        return $this->_get($code, $subNode, $remainingParts, $info);
                    } else {
                        return $info;
                    }
                }
            }
        }

        //Probably a complex glob, try again with the same node, and next code part
        if (!empty($remainingParts)) {
            return $this->_get($code, $currentNode, $remainingParts, $info);
        }

        // Now we are at the end of the tree
        return $info;
    }



    /**
     * @param array $partialInfo
     * @param array|null $node
     * @return array
     */
    private function fillMissingProperties(array $partialInfo, ?array $node = null): array
    {
        foreach (self::REQUIRED_PROPERTIES as $prop) {
            if ($node && isset($node[$prop])) {
                $partialInfo[$prop] = $node[$prop];
            } else if (!isset($partialInfo[$prop]) || $partialInfo[$prop] === '') {
                $partialInfo[$prop] = match ($prop) {
                    'active' => true,
                    'severity' => 3,
                    default => ''
                };
            }
        }
        return $partialInfo;
    }
}


