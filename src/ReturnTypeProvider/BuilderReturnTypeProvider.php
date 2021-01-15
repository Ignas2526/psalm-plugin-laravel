<?php declare(strict_types=1);

namespace Psalm\LaravelPlugin\ReturnTypeProvider;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\MethodCall;
use Psalm\CodeLocation;
use Psalm\Context;
use Psalm\Internal\MethodIdentifier;
use Psalm\LaravelPlugin\Plugin;
use Psalm\Plugin\Hook\MethodReturnTypeProviderInterface;
use Psalm\Plugin\Hook\PropertyExistenceProviderInterface;
use Psalm\Plugin\Hook\PropertyTypeProviderInterface;
use Psalm\Plugin\Hook\AfterClassLikeVisitInterface;
use Psalm\StatementsSource;
use Psalm\Type;
use Psalm\Type\Union;
use function in_array;
use function strtolower;

final class BuilderReturnTypeProvider implements MethodReturnTypeProviderInterface
{
    /**
     * @return array<string>
     */
    public static function getClassLikeNames(): array
    {
        return [Builder::class];
    }

    public static function getMethodReturnType(
        StatementsSource $source,
        string $fq_classlike_name,
        string $method_name_lowercase,
        array $call_args,
        Context $context,
        CodeLocation $code_location,
        array $template_type_parameters = null,
        string $called_fq_classlike_name = null,
        string $called_method_name_lowercase = null
    ) : ?Type\Union {
        if (!$source instanceof \Psalm\Internal\Analyzer\StatementsAnalyzer) {
            return null;
        }

        if (!$method_name_lowercase) {
            return null;
        }

        if (in_array($method_name_lowercase, ['__get', '__set', '__call', '__callStatic'], true)) {
            return null;
        }

        // Ensure that Builder instance has template parameter
        if (!isset($template_type_parameters[0])) {
            return null;
        }

        $self_class = '';
        $typeUnion = $source->getCodebase()->methods->getMethodReturnType(new MethodIdentifier(Builder::class, $method_name_lowercase), $self_class, null, $call_args);

        // Assert that retrun type matches the `@return self<TModel>` or Illuminate\Database\Eloquent\Builder<TModel>
        if ($typeUnion->__toString() !== 'Illuminate\Database\Eloquent\Builder<TModel>') {
            return null;
        }

        // Patch incomming TModel value to never be static and never have extra types
        $templateTypeUnion = $template_type_parameters[0]->getAtomicTypes();
        $templateType = reset($templateTypeUnion);
        $templateType->was_static = false;
        $templateType->extra_types = null;


        $types = $typeUnion->getAtomicTypes();
        // Get first type , the Illuminate\Database\Eloquent\Builder
        $type = reset($types);

        if (!$type instanceof \Psalm\Type\Atomic\TGenericObject) {
            return null;
        }

        // Replace TModel.
        $type->type_params = $template_type_parameters;
        
        return $typeUnion;
    }
}
