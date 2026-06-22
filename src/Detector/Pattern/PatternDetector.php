<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern;

use SineFine\Mnemosyne\Detector\Pattern\Model\PatternResult;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;

interface PatternDetector
{
    public function detect(GraphQuery $graph): PatternResult;
}

