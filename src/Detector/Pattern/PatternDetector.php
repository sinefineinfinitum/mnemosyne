<?php declare(strict_types=1);

namespace SineFine\Ponymator\Detector\Pattern;

use SineFine\Ponymator\Detector\Pattern\Model\PatternResult;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;

interface PatternDetector
{
    public function detect(GraphQuery $graph): PatternResult;
}

