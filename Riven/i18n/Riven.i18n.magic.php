<?php

// While it's good form to do this anyway, this line MUST be here or the entire wiki will come crashing to a halt
// whenever you try to add new magic words.
$magicWords = [];

$magicWords['en'] = [
	Riven::AV_ORIGINAL => [0, 'original'],
	Riven::AV_RECURSIVE => [0, 'recursive'],
	Riven::AV_SMART => [0, 'smart'],
	Riven::AV_TOP => [0, 'top'],

	Riven::NA_ALLOWEMPTY => [0, 'allowempty'],
	Riven::NA_CLEANIMG => [0, 'cleanimages'],
	Riven::NA_DELIMITER => [0, 'delimiter', ':delimiter'],
	Riven::NA_EXPLODE => [0, 'explode', ':explode'],
	Riven::NA_MODE => [0, 'mode'],
	Riven::NA_PROTROWS => [0, 'protectrows'],
	Riven::NA_SEED => [0, 'seed'],

	Riven::PF_ARG => [0, 'arg'],
	Riven::PF_EXPLODEARGS => [0, 'explodeargs'],
	Riven::PF_FINDFIRST => [0, 'findfirst'],
	Riven::PF_IFEXISTX => [0, 'ifexistx'],
	Riven::PF_INCLUDE => [0, 'include'],
	Riven::PF_PICKFROM => [0, 'pickfrom'],
	Riven::PF_RAND => [0, 'rand'],
	Riven::PF_SPLITARGS => [0, 'splitargs'],
	Riven::PF_TRIMLINKS => [0, 'trimlinks'],

	Riven::TG_CLEANSPACE => [0, 'cleanspace'],
	Riven::TG_CLEANTABLE => [0, 'cleantable'],

	Riven::VR_SKINNAME => [1, 'skin'],
];
