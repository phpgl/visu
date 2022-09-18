<?php 

namespace VISU\Command;

use VISU\Maker\Exception\MakerException;
use VISU\Maker\Maker;

class MakerCommand extends Command
{
    /**
     * The commands decsription displayed when listening commands
     * if null it will fallback to the description property
     */
    protected ?string $descriptionShort = 'Makes stuff, like classes, commands and others';

    /**
     * The command description 
     */
    protected string $description = 'Maker is an extendable toolkit to generate classes / resources for VISU projects.';

    /**
     * An array of expected arguments 
     *
     * @var array<string, array<string, mixed>>
     */
    protected $expectedArguments = [
        'type' => [
            'description' => 'The maker type (class, command etc..)',
            'castTo' => 'string'
        ],
        'dry-run' => [
            'prefix'  => 'd',
            'longPrefix'  => 'dry-run',
            'description' => 'Don`t apply the changes, just print them out.',
            'noValue'     => true,
        ],
        'force' => [
            'prefix'  => 'f',
            'longPrefix'  => 'force',
            'description' => 'Overrides changes without asking..',
            'noValue'     => true,
        ],
    ];
    
    /**
     * Code Generator / Maker
     */
    private Maker $maker;

    /**
     * Column length
     */
    private int $shellColLength = 80;

    /**
     * Constructor
     */
    public function __construct(Maker $maker)
    {
        $this->maker = $maker;

        $this->shellColLength = ((int) shell_exec('tput cols')) ?: 80;
    }

     /**
     * Returns the commands description
     */
    public function getCommandDescription() : string
    {
        $buffer = parent::getCommandDescription();

        $buffer .= PHP_EOL . PHP_EOL . '<blue>Available generators types:</blue>' . PHP_EOL;
        foreach($this->maker->getAvailableGeneratorTypes() as $type) {
            $buffer .= ' - <green>' . $type . '</green>' . PHP_EOL;
        }

        return $buffer;
    }

    /**
     * Returns the expected arguments and by default the class property
     *
     * @param array<mixed>              $argumentVector The unparsed argument vector, allowing for subcommands to be handled.
     * @return array<string, array<string, mixed>>
     */
    public function getExpectedArguments(array $argumentVector) : array
    {
        $generatorExpectedArguments = [];

        // if a generator of the type is present merge the expected arguments
        if ($this->maker->hasGenerator($argumentVector[1] ?? '')) {
            $generatorExpectedArguments = $this->maker->getExpectedArguments($argumentVector[1]);
        }

        return array_merge($this->expectedArguments, $generatorExpectedArguments);
    }

    /**
     * removes the absolute path prefix from the given path
     */
    private function removeAbsolutePathPrefix(string $path) : string
    {
        return substr($path, strlen(VISU_PATH_ROOT));
    }

    /**
     * Execute this command 
     */
    public function execute()
    {
        $generatorType = (string) $this->cli->arguments->get('type');

        if (!$this->maker->hasGenerator($generatorType)) {
            $this->cli->error(sprintf('There is no generator named "%s"', $generatorType));
        }

        try {
            $parameters = $this->cli->arguments->toArray();

            // allow the generator to evaluate the given paramters before generating 
            $this->maker->evaluateParamters($generatorType, $parameters, $this->cli);

            $changes = $this->maker->buildChanges($generatorType, $parameters);

            if ($this->cli->arguments->get('dry-run')) 
            {
                foreach($changes as $change) {
                    $this->cli->out(str_repeat('-', $this->shellColLength));
                    $this->cli->out('<blue>' . $this->removeAbsolutePathPrefix($change->filepath) . '</blue>');
                    $this->cli->out(str_repeat('-', $this->shellColLength));
                    $this->cli->out('<green>' . str_repeat('+', $this->shellColLength) . '</green>');
                    $this->cli->out($change->code);
                    $this->cli->out('<green>' . str_repeat('+', $this->shellColLength) . '</green>');
                }
            }
            else 
            {
                $this->maker->applyChanges($changes, (bool) $this->cli->arguments->get('force'), $this->cli);
            }
        }
        catch (MakerException $e) {
            $this->cli->error($e->getMessage());
        }
    }
}
