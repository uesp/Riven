<?php
// namespace MediaWiki\Extension\MetaTemplate;
use MediaWiki\MediaWikiServices;
// use MediaWiki\DatabaseUpdater;

// TODO: Add {{#define/local/preview:a=b|c=d}}
/**
 * [Description MetaTemplateHooks]
 */
class RivenHooks
{
	private static $tagInfo = [
		Riven::TG_CLEANSPACE => 'Riven::doCleanSpace',
		Riven::TG_CLEANTABLE => 'Riven::doCleanTable'
	];

	private static $doOnce = true;

	// This is the best place to disable individual magic words;
	// To disable all magic words, disable the hook that calls this function
	/**
	 * onMagicWordwgVariableIDs
	 *
	 * @param array $aCustomVariableIds
	 *
	 * @return void
	 */
	public static function onMagicWordwgVariableIDs(array &$aCustomVariableIds)
	{
		$aCustomVariableIds[] = Riven::VR_SKINNAME;
	}

	// Register any render callbacks with the parser
	/**
	 * onParserFirstCallInit
	 *
	 * @param Parser $parser
	 *
	 * @return void
	 */
	public static function onParserFirstCallInit(Parser $parser)
	{
		if (true) {
			self::$doOnce = false;

			ParserHelper::init();
			self::initParserFunctions($parser);
			self::initTagFunctions($parser);
			Riven::init();
		}
	}
	/**
	 * onParserGetVariableValueSwitch
	 *
	 * @param Parser $parser
	 * @param array $variableCache
	 * @param mixed $magicWordId
	 * @param mixed $ret
	 * @param PPFrame $frame
	 *
	 * @return string
	 */
	public static function onParserGetVariableValueSwitch(Parser $parser, array &$variableCache, $magicWordId, &$ret, PPFrame $frame)
	{
		switch ($magicWordId) {
			case Riven::VR_SKINNAME:
				$ret = Riven::doSkinName();
				$variableCache[$magicWordId] = $ret;
				break;
		}
	}

	/**
	 * initParserFunctions
	 *
	 * @param Parser $parser
	 *
	 * @return void
	 */
	private static function initParserFunctions(Parser $parser)
	{
		$parser->setFunctionHook(Riven::PF_ARG, 'Riven::doArg', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(Riven::PF_IFEXISTX, 'Riven::doIfExistX', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(Riven::PF_INCLUDE, 'Riven::doInclude', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(Riven::PF_PICKFROM, 'Riven::doPickFrom', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(Riven::PF_RAND, 'Riven::doRand', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(Riven::PF_SPLITARGS, 'Riven::doSplitargs', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(Riven::PF_TRIMLINKS, 'Riven::doTrimLinks', SFH_OBJECT_ARGS);
	}

	/**
	 * initTagFunctions
	 *
	 * @param Parser $parser
	 *
	 * @return void
	 */
	private static function initTagFunctions(Parser $parser)
	{
		foreach (self::$tagInfo as $k => $v) {
			ParserHelper::setAllSynonyms($parser, $k, $v);
		}
	}
}
