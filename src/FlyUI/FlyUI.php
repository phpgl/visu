<?php

namespace VISU\FlyUI;

use Closure;
use GL\Math\Vec2;
use GL\VectorGraphics\VGColor;
use GL\VectorGraphics\VGContext;
use VISU\FlyUI\Exception\FlyUiInitException;
use VISU\OS\Input;
use VISU\Signal\Dispatcher;

/**
 * FlyUI is intended as a simple to use immediate mode GUI library primarly for
 * prototyping, debugging or very small projects.
 * 
 * The rendering architecture is designed for convenience and not performance.
 * So please be not supprised when you build a complex UI that it will eat up 
 * your system resources more then the rest of your application.
 */
class FlyUI
{
    /**
     * Global FlyUI instance
     */
    public static FlyUI $instance;

    /**
     * Initializes the global FlyUI instance
     */
    public static function initailize(VGContext $vgContext, Dispatcher $dispatcher, Input $input) : void {
        self::$instance = new FlyUI($vgContext, $dispatcher, $input);

        if ($vgContext->createFont('inter-regular', VISU_PATH_FRAMEWORK_RESOURCES_FONT . '/inter/Inter-Regular.ttf') === -1) {
            throw new FlyUiInitException('Could not load the "Inter-Regular.ttf" font file.');
        }

        if ($vgContext->createFont('inter-semibold', VISU_PATH_FRAMEWORK_RESOURCES_FONT . '/inter/Inter-SemiBold.ttf') === -1) {
            throw new FlyUiInitException('Could not load the "Inter-Bold.ttf" font file.');
        }
    }

    /**
     * Begins a FlyUI frame
     * 
     * @param Vec2 $resolution The resolution of the frame (in points not pixels)
     */
    public static function beginFrame(Vec2 $resolution, float $contentScale = 1.0) : void {
        if (!isset(self::$instance)) {
            throw new \Exception('FlyUI has not been initialized, call FlyUI::initialize() first');
        }

        self::$instance->internalBeginFrame($resolution, $contentScale);
    }

    /**
     * Ends a FlyUI frame
     */
    public static function endFrame() : void {
        if (!isset(self::$instance)) {
            throw new \Exception('FlyUI has not been initialized, call FlyUI::initialize() first');
        }

        self::$instance->internalEndFrame();
    }

    /**
     * Starts a layout element. 
     * 
     * A layout is a container view that arranges its children either vertically or horizontally.
     * 
     * There is support for different sizing modes:
     *  - Fixed: a fixed size in points
     *  - Fill: takes up all available space
     *  - Fit: sizes to the content
     * 
     * The layout can then also align its children to topLeft, topCenter etc...
     */
    public static function beginLayout(?Vec2 $padding = null) : FUILayout
    {
        $layout = new FUILayout($padding);
        self::$instance->pushView($layout);
        return $layout;
    }

    /**
     * Starts a horiazontal layout where elements are stacked after each other from left to right
     */
    public static function beginHorizontalStack(?float $spacing = null, ?Vec2 $padding = null) : FUILayout
    {
        $layout = new FUILayout($padding);
        $layout
            ->horizontalFit()
            ->spacing($spacing ?? self::$instance->theme->spacing)
            ->flow(FUILayoutFlow::horizontal);
        self::$instance->pushView($layout);
        return $layout;
    }

    /**
     * Starts a section 
     * A section is a container with a title and some content underneath
     */
    public static function beginSection(string $title) : FUILayout
    {
        $l = self::beginLayout()
            ->horizontalFill()
            ->verticalFit()
            ->spacing(self::$instance->theme->sectionSpacing);

        self::$instance->text(mb_strtoupper($title), self::$instance->theme->sectionHeaderTextColor)
            ->fontSize(self::$instance->theme->sectionHeaderFontSize)
            ->bold();

        return $l;
    }

    /**
     * Begins a new card view
     */
    public static function beginCardView() : FUICard
    {
        $view = new FUICard();
        self::$instance->pushView($view);
        return $view;
    }

    /**
     * Ends the current view
     */
    public static function end() : void
    {
        self::$instance->popView();
    }

    /**
     * Creates a X space element
     */
    public static function spaceX(float $width) : FUISpace
    {
        $view = new FUISpace(new Vec2($width, 0));
        self::$instance->addChildView($view);
        return $view;
    }

    /**
     * Creates a Y space element
     */
    public static function spaceY(float $height) : FUISpace
    {
        $view = new FUISpace(new Vec2(0, $height));
        self::$instance->addChildView($view);
        return $view;
    }

    /**
     * Creates a text element
     */
    public static function text(string $text, ?VGColor $color = null) : FUIText
    {
        $view = new FUIText($text, $color);
        self::$instance->addChildView($view);
        return $view;
    }

    /**
     * Creates a button element
     */
    public static function button(string $text, Closure $onClick) : FUIButton
    {
        $view = new FUIButton($text, $onClick);
        self::$instance->addChildView($view);

        return $view;
    }

    /**
     * Creates a button group element
     */
    public static function buttonGroup(array $options, ?string $selectedOption = null, ?Closure $onSelect = null) : FUIButtonGroup
    {
        $view = new FUIButtonGroup($options, $selectedOption, $onSelect);
        self::$instance->addChildView($view);
        return $view;
    }

    /**
     * Creates a checkbox element
     * 
     * @param string $text The text to display next to the checkbox
     * @param bool $checked Reference to the checked state
     * @param Closure(bool):void $callback Callback that is called when the checkbox is toggled
     */
    public static function checkbox(string $text, bool &$checked, ?Closure $callback = null) : FUICheckbox
    {
        $view = new FUICheckbox($text, $checked, $callback);
        self::$instance->addChildView($view);
        return $view;
    }

    /**
     * The Theme currently used
     */
    public FUITheme $theme;
    
    /**
     * The current tree of views 
     * This functions as a stack where the last element is the current view
     * 
     * @var array<FUIView>
     */
    private array $viewTree = [];

    private FUIView $currentView;

    private Vec2 $currentResolution;

    private float $currentContentScale = 1.0;

    /**
     * If true, FLyUI will create and end VGContext frames itself
     */
    private bool $selfManageVGContext = false;

    /**
     * Constructor
     */
    public function __construct(
        private VGContext $vgContext,
        private Dispatcher $dispatcher,
        private Input $input,
        ?FUITheme $theme = null
    )
    {
        // assing the theme, create a default one if none is provided
        $this->theme = $theme ?? new FUITheme();
    }

    /**
     * Sets if FlyUI should manage the VGContext frames itself
     * 
     * When enabled FlyUI will call beginFrame and endFrame itself.
     */
    public function setSelfManageVGContext(bool $value) : void
    {
        $this->selfManageVGContext = $value;
    }

    /**
     * Adds a view to the view tree
     */
    public function pushView(FUIView $view) : void
    {
        // add the view as child view of the current view
        // if this is the operation adding the root view, we skip this
        if (isset($this->viewTree[0])) {
            $this->addChildView($view);
        }

        $this->viewTree[] = $view;
        $this->currentView = $view;
    }

    /**
     * Adds the given view as a child of the current view
     */
    public function addChildView(FUIView $view) : void
    {
        $this->currentView->children[] = $view;
    }

    /**
     * Pops the last view from the view tree
     */
    public function popView() : void
    {
        if (count($this->viewTree) <= 1) {
            throw new FUIException('Cannot pop the root view');
        }

        array_pop($this->viewTree);
        $this->currentView = end($this->viewTree); // @phpstan-ignore-line (we know there is at least one element)
    }

    /**
     * Returns the current view
     */
    public function currentView() : FUIView
    {
        return $this->currentView;
    }

    /** 
     * Start a new UI frame
     */
    private function internalBeginFrame(Vec2 $resolution, float $contentScale = 1.0) : void
    {
        // hold current viewport reference
        $this->currentResolution = $resolution;
        $this->currentContentScale = $contentScale;

        // reset view tree
        $this->viewTree = [];

        // push the root view
        $root = new FUILayout();
        $root->verticalFill();
        $root->horizontalFill();
        $this->pushView($root);

        // begin the VGContext frame
        if ($this->selfManageVGContext) {
            $this->vgContext->beginFrame($resolution->x, $resolution->y, $contentScale);
        }
    }

    /**
     * End a UI frame (Dispatches the rendering of the views)
     */
    private function internalEndFrame() : void
    {
        $ctx = new FUIRenderContext($this->vgContext, $this->input, $this->theme);
        $ctx->containerSize = $this->currentResolution;

        $this->vgContext->reset();

        // set the default font face
        $ctx->ensureRegularFontFace();

        // let all views render itself
        $this->viewTree[0]->render($ctx);

        // end the VGContext frame
        if ($this->selfManageVGContext) {
            $this->vgContext->endFrame();
        }
    }
}