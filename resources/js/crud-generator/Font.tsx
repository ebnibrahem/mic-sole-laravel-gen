import React from 'react';
import { Button } from "@/components/ui/button";
import { Sun, Moon } from 'lucide-react';
import { Select, SelectItem, SelectTrigger, SelectContent, SelectValue } from "@/components/ui/select";

interface FontProps {
  font: string;
  setFont: (font: string) => void;
  fontList: string[];
  dark: boolean;
  setDark: (dark: boolean) => void;
}

export default function Font({ font, setFont, fontList, dark, setDark }: FontProps) {
  const codeFontStyle = {
    fontFamily: `${font}, ${fontList.filter(f => f !== font).join(', ')}`
  };
  return (
    <div className="flex items-center gap-4">
      <Select value={font} onValueChange={setFont}>
        <SelectTrigger className="w-48" style={codeFontStyle}>
          <SelectValue placeholder="Select font" />
        </SelectTrigger>
        <SelectContent className="bg-white text-slate-900 dark:bg-slate-800 dark:text-slate-100" style={codeFontStyle}>
          {fontList.map(f => (
            <SelectItem key={f} value={f} style={{ fontFamily: `${f}, ${fontList.filter(ff => ff !== f).join(', ')}` }}>{f}</SelectItem>
          ))}
        </SelectContent>
      </Select>
      <Button
        variant="ghost"
        size="icon"
        aria-label="Toggle dark mode"
        onClick={() => setDark(!dark)}
      >
        {dark ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
      </Button>
    </div>
  );
}
