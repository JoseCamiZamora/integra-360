<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * Vehicle body type. The PESV uses it to pick the inspection checklist (I360-05) and document types may apply only to some types.
 */
enum VehicleType: string
{
    case Tractocamion = 'tractocamion';
    case Rigido = 'rigido';
    case Volqueta = 'volqueta';
    case Semirremolque = 'semirremolque';
    case Camioneta = 'camioneta';
    case Automovil = 'automovil';
    case Bus = 'bus';
    case Buseta = 'buseta';
    case Microbus = 'microbus';
    case Motocicleta = 'motocicleta';

    public function label(): string
    {
        return __('core::enums.vehicle_type.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
