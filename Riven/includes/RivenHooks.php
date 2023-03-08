<?php

/* To disable tags, comment out lines in $tagInfo.
 * To disable variables, comment out lines in onMagicWordwgVariableIDs.
 * To disable parser functions, comment out lines in initParserFunctions.
 */

/**
 * MediaWiki hooks for Riven.
 */
class RivenHooks /* implements
	\MediaWiki\Hook\ParserFirstCallInitHook */
{
	public const PF_ARG         = 'arg'; // From DynamicFunctions
	public const PF_EXPLODEARGS = 'explodeargs';
	public const PF_FINDFIRST   = 'findfirst';
	public const PF_IFEXISTX    = 'ifexistx';
	public const PF_INCLUDE     = 'include';
	public const PF_PICKFROM    = 'pickfrom';
	public const PF_RAND        = 'rand'; // From DynamicFunctions
	public const PF_SPLITARGS   = 'splitargs';
	public const PF_TRIMLINKS   = 'trimlinks';

	public const TG_CLEANSPACE = 'riven-cleanspace';
	public const TG_CLEANTABLE = 'riven-cleantable';

	public const VR_SKINNAME = 'riven-skinname'; // From DynamicFunctions

	/**
	 * Register variables.
	 *
	 * @param array $aCustomVariableIds The current list of variables.
	 *
	 * @return void
	 *
	 */
	public static function onMagicWordwgVariableIDs(array &$aCustomVariableIds): void
	{
		$aCustomVariableIds[] = self::VR_SKINNAME;
	}

	/**
	 * Parser initialization.
	 *
	 * @param Parser $parser The parser in use.
	 *
	 * @return void
	 *
	 */
	public static function onParserFirstCallInit(Parser $parser): void
	{
		self::initParserFunctions($parser);
		self::initTagFunctions($parser);
	}

	/**
	 * Get variable values.
	 *
	 * @param Parser $parser The parser in use.
	 * @param array $variableCache The magic word variable cache.
	 * @param mixed $magicWordId The magic word id being sought.
	 * @param mixed $ret Return value.
	 * @param PPFrame $frame The frame in use.
	 *
	 * @return bool Always true, per Wikipedia documentation.
	 *
	 */
	public static function onParserGetVariableValueSwitch(Parser $parser, array &$variableCache, $magicWordId, &$ret, PPFrame $frame): bool
	{
		switch ($magicWordId) {
			case self::VR_SKINNAME:
				$ret = Riven::doSkinName($parser);

				// Cached, but only for the current request (presumably), since user could change their settings at any
				// time.
				$variableCache[$magicWordId] = $ret;
				$parser->getOutput()->updateCacheExpiry(5);
		}

		return true;
	}

	/**
	 * Register all parser functions.
	 *
	 * @param Parser $parser The parser in use.
	 *
	 * @return void
	 *
	 */
	private static function initParserFunctions(Parser $parser): void
	{
		$parser->setFunctionHook(self::PF_ARG, 'Riven::doArg', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(self::PF_EXPLODEARGS, 'Riven::doExplodeargs', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(self::PF_FINDFIRST, 'Riven::doFindFirst', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(self::PF_IFEXISTX, 'Riven::doIfExistX', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(self::PF_INCLUDE, 'Riven::doInclude', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(self::PF_PICKFROM, 'Riven::doPickFrom', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(self::PF_RAND, 'Riven::doRand', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(self::PF_SPLITARGS, 'Riven::doSplitargs', SFH_OBJECT_ARGS);
		$parser->setFunctionHook(self::PF_TRIMLINKS, 'Riven::doTrimLinks', SFH_OBJECT_ARGS);
	}

	/**
	 * Register all tag functions.
	 *
	 * @param Parser $parser The parser in use.
	 *
	 * @return void
	 *
	 */
	private static function initTagFunctions(Parser $parser): void
	{
		$parser->setHook(self::TG_CLEANSPACE, 'Riven::doCleanSpace');
		$parser->setHook(self::TG_CLEANTABLE, 'Riven::doCleanTable');
	}
}
