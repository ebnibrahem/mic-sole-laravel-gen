import React, { useState } from 'react';
import { Popover, PopoverTrigger, PopoverContent } from '@/components/ui/popover';
import { Checkbox } from '@/components/ui/checkbox';
import { Button } from '@/components/ui/button';
import { Check, ChevronDown } from 'lucide-react';
import type { SelectMultipleProps } from './types';

interface SelectMultiplePropsWithBadge extends SelectMultipleProps {
  badgeNumbers?: number;
}

export default function SelectMultiple({ options, value, onChange, badgeNumbers = 3 }: SelectMultiplePropsWithBadge) {
  const [open, setOpen] = useState(false);

  const allChecked = value.length === options.length;
  const handleToggle = (option: string) => {
    if (value.includes(option)) {
      onChange(value.filter(v => v !== option));
    } else {
      onChange([...value, option]);
    }
  };
  const handleCheckAll = () => {
    if (allChecked) {
      onChange([]);
    } else {
      onChange(options);
    }
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          className="w-40 justify-between px-2 py-1 h-8 text-xs"
          type="button"
        >
          {value.length === 0 ? (
            'Select...'
          ) : (
            <>
              {value.slice(0, badgeNumbers).map(v => (
                <span key={v} className="inline-flex items-center bg-slate-200 dark:bg-slate-700 rounded px-1 mx-0.5 text-xs">
                  {v}
                </span>
              ))}
              {value.length > badgeNumbers && (
                <span className="inline-flex items-center bg-slate-400 dark:bg-slate-600 rounded px-1 mx-0.5 text-xs">
                  +{value.length - badgeNumbers}
                </span>
              )}
            </>
          )}
          <ChevronDown className="ml-2 w-4 h-4 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-48 p-2">
        <label
          className={`flex items-center gap-2 py-1 cursor-pointer rounded px-2 font-semibold ${allChecked ? 'bg-slate-100 dark:bg-slate-800' : 'bg-white dark:bg-slate-900'}`}
          onClick={handleCheckAll}
        >
          <Checkbox checked={allChecked} readOnly />
          <span className="text-xs">Check All</span>
        </label>
        <div className="border-b border-slate-200 dark:border-slate-700 my-1" />
        {options.map(option => {
          const selected = value.includes(option);
          return (
            <label
              key={option}
              className={`flex items-center gap-2 py-1 cursor-pointer rounded px-2 ${selected ? 'bg-slate-100 dark:bg-slate-800' : 'bg-white dark:bg-slate-900'}`}
            >
              <Checkbox
                checked={selected}
                onCheckedChange={() => handleToggle(option)}
              />
              <span className="text-xs">{option.charAt(0).toUpperCase() + option.slice(1)}</span>
              {selected && <Check className="w-3 h-3 text-primary-500" />}
            </label>
          );
        })}
      </PopoverContent>
    </Popover>
  );
}
