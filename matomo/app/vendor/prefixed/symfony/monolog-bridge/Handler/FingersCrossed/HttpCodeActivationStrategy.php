<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Matomo\Dependencies\Symfony\Bridge\Monolog\Handler\FingersCrossed;

use Matomo\Dependencies\Monolog\Handler\FingersCrossed\ActivationStrategyInterface;
use Matomo\Dependencies\Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Matomo\Dependencies\Symfony\Component\HttpFoundation\RequestStack;
use Matomo\Dependencies\Symfony\Component\HttpKernel\Exception\HttpException;
/**
 * Activation strategy that ignores certain HTTP codes.
 *
 * @author Shaun Simmons <shaun@envysphere.com>
 * @author Pierrick Vignand <pierrick.vignand@gmail.com>
 *
 * @final
 */
class HttpCodeActivationStrategy extends ErrorLevelActivationStrategy implements ActivationStrategyInterface
{
    private $inner;
    private $exclusions;
    private $requestStack;
    /**
     * @param array                                  $exclusions each exclusion must have a "code" and "urls" keys
     * @param ActivationStrategyInterface|int|string $inner      an ActivationStrategyInterface to decorate
     */
    public function __construct(RequestStack $requestStack, array $exclusions, $inner)
    {
        if (!$inner instanceof ActivationStrategyInterface) {
            trigger_deprecation('symfony/monolog-bridge', '5.2', 'Passing an actionLevel (int|string) as constructor\'s 3rd argument of "%s" is deprecated, "%s" expected.', __CLASS__, ActivationStrategyInterface::class);
            $actionLevel = $inner;
            $inner = new ErrorLevelActivationStrategy($actionLevel);
        }
        foreach ($exclusions as $exclusion) {
            if (!\array_key_exists('code', $exclusion)) {
                throw new \LogicException('An exclusion must have a "code" key.');
            }
            if (!\array_key_exists('urls', $exclusion)) {
                throw new \LogicException('An exclusion must have a "urls" key.');
            }
        }
        $this->inner = $inner;
        $this->requestStack = $requestStack;
        $this->exclusions = $exclusions;
    }
    public function isHandlerActivated(array $record) : bool
    {
        $isActivated = $this->inner->isHandlerActivated($record);
        if ($isActivated && isset($record['context']['exception']) && $record['context']['exception'] instanceof HttpException && ($request = $this->requestStack->getMainRequest())) {
            foreach ($this->exclusions as $exclusion) {
                if ($record['context']['exception']->getStatusCode() !== $exclusion['code']) {
                    continue;
                }
                if (\count($exclusion['urls'])) {
                    return !preg_match('{(' . implode('|', $exclusion['urls']) . ')}i', $request->getPathInfo());
                }
                return \false;
            }
        }
        return $isActivated;
    }
}
