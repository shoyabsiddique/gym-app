import { useEffect, useRef, useState } from 'react';
import { toast } from 'react-hot-toast';
import api from '../../lib/api';
import { Card } from '../../components/ui/Card';
import { Button } from '../../components/ui/Button';
import type { Menu, MealType } from '../../types';

const MEAL_ORDER: MealType[] = ['breakfast', 'lunch', 'snacks', 'dinner'];
const MEAL_COLORS: Record<MealType, string> = {
  breakfast: 'text-yellow-400',
  lunch:     'text-emerald-400',
  snacks:    'text-orange-400',
  dinner:    'text-purple-400',
};

function thisMonday(): string {
  const d = new Date();
  const day = d.getDay();
  const diff = day === 0 ? -6 : 1 - day;
  d.setDate(d.getDate() + diff);
  return d.toISOString().slice(0, 10);
}

// Gemini returns: date → meal → counter → [dishes]
type ParsedMenu = Record<string, Record<string, Record<string, string[]>>>;

interface UploadResult {
  message: string;
  summary: { menus: number; new_dishes: number; existing_dishes: number; nutrition_fetched: number };
  parsed: ParsedMenu;
  stats: { days: number; total_dish_slots: number; unique_dishes: number };
}

export function AdminMenus() {
  const [menus, setMenus] = useState<Menu[]>([]);
  const [loading, setLoading] = useState(true);
  const [weekStart, setWeekStart] = useState(thisMonday());
  const [tab, setTab] = useState<'view' | 'upload'>('view');

  const [files, setFiles] = useState<File[]>([]);
  const [previews, setPreviews] = useState<string[]>([]);
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState('');
  const [result, setResult] = useState<UploadResult | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [showJson, setShowJson] = useState(false);
  const [importText, setImportText] = useState('');
  const [importingJson, setImportingJson] = useState(false);

  const load = () => {
    api.get('/menus', { params: { week_start: weekStart } })
      .then(r => setMenus(r.data))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [weekStart]);

  const shiftWeek = (delta: number) => {
    const d = new Date(weekStart);
    d.setDate(d.getDate() + delta * 7);
    setWeekStart(d.toISOString().slice(0, 10));
  };

  const weekLabel = () => {
    const start = new Date(weekStart + 'T00:00:00');
    const end = new Date(weekStart + 'T00:00:00');
    end.setDate(end.getDate() + 4);
    const fmt = (d: Date) => d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    return `${fmt(start)} – ${fmt(end)}, ${start.getFullYear()}`;
  };

  const addFiles = (newFiles: FileList | File[]) => {
    const imageFiles = Array.from(newFiles).filter(f => f.type.startsWith('image/'));
    setFiles(prev => [...prev, ...imageFiles]);
    const urls = imageFiles.map(f => URL.createObjectURL(f));
    setPreviews(prev => [...prev, ...urls]);
    setResult(null);
  };

  const removeFile = (index: number) => {
    URL.revokeObjectURL(previews[index]);
    setFiles(prev => prev.filter((_, i) => i !== index));
    setPreviews(prev => prev.filter((_, i) => i !== index));
  };

  const clearAll = () => {
    previews.forEach(u => URL.revokeObjectURL(u));
    setFiles([]);
    setPreviews([]);
    setResult(null);
    setUploadProgress('');
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    addFiles(e.dataTransfer.files);
  };

  const handleUpload = async () => {
    if (!files.length) return;
    setUploading(true);
    setUploadProgress('Uploading images…');

    try {
      const fd = new FormData();
      files.forEach(f => fd.append('images[]', f));
      fd.append('monday_date', weekStart);

      setUploadProgress(`Stitching ${files.length} images into PDF & sending to Gemini…`);

      const r = await api.post<UploadResult>('/menus/upload-images', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
        timeout: 180_000,
      });

      setResult(r.data);
      const s = r.data.summary;
      toast.success(
        `Done! ${s.new_dishes} new dishes · ${s.nutrition_fetched} nutrition profiles · ${s.menus} menu entries`
      );
      setUploadProgress('');
      load();
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Upload failed — check your Gemini API key');
      setUploadProgress('');
    } finally {
      setUploading(false);
    }
  };

  const handleJsonImport = async () => {
    try {
      const data = JSON.parse(importText);
      setImportingJson(true);
      const r = await api.post('/menus/import', { menu: data });
      toast.success(`Imported: ${r.data.summary.menus} menus · ${r.data.summary.dishes} dishes`);
      setShowJson(false);
      setImportText('');
      load();
    } catch (e: any) {
      toast.error(e.message?.includes('JSON') ? 'Invalid JSON' : e.response?.data?.message || 'Import failed');
    } finally {
      setImportingJson(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Delete this menu entry?')) return;
    try {
      await api.delete(`/menus/${id}`);
      toast.success('Deleted');
      setMenus(m => m.filter(x => x.id !== id));
    } catch {
      toast.error('Delete failed');
    }
  };

  // Group: date → mealType → counter → Menu
  const grouped = menus.reduce((acc, m) => {
    const d = m.menu_date.slice(0, 10);
    if (!acc[d]) acc[d] = {};
    if (!acc[d][m.meal_type]) acc[d][m.meal_type] = {};
    acc[d][m.meal_type][m.counter] = m;
    return acc;
  }, {} as Record<string, Record<string, Record<string, Menu>>>);

  return (
    <div className="max-w-5xl mx-auto px-4 py-6">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 className="text-xl font-bold text-white">Weekly Menus</h1>
        <div className="flex gap-2">
          <Button variant={tab === 'view' ? 'primary' : 'ghost'} size="sm" onClick={() => setTab('view')}>
            View
          </Button>
          <Button variant={tab === 'upload' ? 'primary' : 'ghost'} size="sm" onClick={() => setTab('upload')}>
            + Upload Menu Images
          </Button>
          <Button variant="ghost" size="sm" onClick={() => setShowJson(!showJson)}>
            JSON
          </Button>
        </div>
      </div>

      {/* JSON fallback */}
      {showJson && (
        <Card className="mb-6 border border-gray-700">
          <h2 className="text-sm font-semibold text-gray-300 mb-2">Paste Menu JSON</h2>
          <textarea
            className="w-full bg-gray-900 border border-gray-700 text-gray-200 rounded-lg p-3 text-xs font-mono h-40 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            placeholder={'{"2026-06-23": {"lunch": {"Coriander": ["Dal Tadka", "Rice"]}}}'}
            value={importText}
            onChange={e => setImportText(e.target.value)}
          />
          <div className="flex gap-2 mt-2">
            <Button size="sm" onClick={handleJsonImport} loading={importingJson}>Import</Button>
            <Button variant="ghost" size="sm" onClick={() => setShowJson(false)}>Cancel</Button>
          </div>
        </Card>
      )}

      {/* Upload tab */}
      {tab === 'upload' && (
        <div className="space-y-4 mb-6">
          {/* Week selector */}
          <Card>
            <h2 className="text-sm font-semibold text-gray-300 mb-3">Target Week</h2>
            <div className="flex items-center gap-3 flex-wrap">
              <button onClick={() => shiftWeek(-1)}
                className="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded-lg text-sm">← Prev</button>
              <div className="flex items-center gap-2 bg-gray-800 rounded-lg px-3 py-1.5">
                <span className="text-sm text-emerald-300 font-medium">{weekLabel()}</span>
                <span className="text-xs text-gray-500">(Mon–Fri)</span>
              </div>
              <button onClick={() => shiftWeek(1)}
                className="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded-lg text-sm">Next →</button>
              <button onClick={() => setWeekStart(thisMonday())}
                className="px-3 py-1.5 text-xs text-gray-400 hover:text-gray-200 underline">This week</button>
            </div>
          </Card>

          {/* Drop zone */}
          <Card>
            <h2 className="text-sm font-semibold text-gray-300 mb-3">
              Upload Menu Photos
              {files.length > 0 && <span className="text-emerald-400 ml-2">({files.length} selected)</span>}
            </h2>
            <div
              className={`relative border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-colors
                ${files.length ? 'border-emerald-500/60 bg-emerald-900/10' : 'border-gray-600 hover:border-gray-500 bg-gray-800/30'}`}
              onClick={() => fileInputRef.current?.click()}
              onDrop={handleDrop}
              onDragOver={e => e.preventDefault()}
            >
              {files.length > 0 ? (
                <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3" onClick={e => e.stopPropagation()}>
                  {previews.map((url, i) => (
                    <div key={i} className="relative group">
                      <img src={url} alt={`Menu ${i + 1}`}
                        className="w-full h-24 object-cover rounded-lg border border-gray-700" />
                      <button onClick={() => removeFile(i)}
                        className="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-600 text-white text-xs rounded-full
                          opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">×</button>
                      <span className="absolute bottom-1 left-1 text-[10px] bg-black/60 text-white px-1 rounded">{i + 1}</span>
                    </div>
                  ))}
                  <div onClick={() => fileInputRef.current?.click()}
                    className="flex items-center justify-center h-24 border-2 border-dashed border-gray-600 rounded-lg cursor-pointer hover:border-gray-500 transition-colors">
                    <span className="text-gray-500 text-2xl">+</span>
                  </div>
                </div>
              ) : (
                <div className="py-8">
                  <div className="text-4xl mb-3">📸</div>
                  <p className="text-gray-400 text-sm">Drop all menu board photos here, or click to browse</p>
                  <p className="text-gray-600 text-xs mt-1">Select multiple images at once · JPG, PNG, WebP · max 10 MB each</p>
                </div>
              )}
              <input ref={fileInputRef} type="file" accept="image/jpeg,image/png,image/webp,image/gif"
                multiple className="hidden" onChange={e => e.target.files && addFiles(e.target.files)} />
            </div>

            {files.length > 0 && !result && (
              <div className="flex items-center gap-3 mt-4">
                <Button onClick={handleUpload} loading={uploading} className="flex-1">
                  {uploading ? uploadProgress : `Upload ${files.length} Image${files.length > 1 ? 's' : ''} → Parse & Import`}
                </Button>
                <Button variant="ghost" size="sm" onClick={clearAll} disabled={uploading}>Clear All</Button>
              </div>
            )}

            {uploading && (
              <div className="mt-3 space-y-2">
                <div className="h-1.5 bg-gray-700 rounded-full overflow-hidden">
                  <div className="h-full bg-emerald-500 rounded-full animate-pulse" style={{ width: '60%' }} />
                </div>
                <p className="text-xs text-gray-400 text-center">{uploadProgress}</p>
              </div>
            )}
          </Card>

          {/* Result with counter grouping */}
          {result && (
            <Card>
              <div className="flex items-center gap-3 mb-4">
                <span className="text-2xl">✅</span>
                <div>
                  <h2 className="text-sm font-semibold text-emerald-300">Import Complete</h2>
                  <p className="text-xs text-gray-400">
                    {result.summary.new_dishes} new dishes · {result.summary.existing_dishes} existing ·
                    {result.summary.nutrition_fetched} nutrition profiles · {result.summary.menus} menu entries
                  </p>
                </div>
              </div>

              <div className="space-y-3">
                {Object.entries(result.parsed).sort(([a], [b]) => a.localeCompare(b)).map(([date, meals]) => (
                  <div key={date} className="bg-gray-900/60 rounded-xl p-4">
                    <h3 className="text-sm font-semibold text-gray-200 mb-3">
                      {new Date(date + 'T00:00:00').toLocaleDateString('en-US', {
                        weekday: 'long', month: 'short', day: 'numeric'
                      })}
                    </h3>
                    {MEAL_ORDER.map(mealType => {
                      const counters = meals[mealType];
                      if (!counters || !Object.keys(counters).length) return null;
                      return (
                        <div key={mealType} className="mb-3">
                          <span className={`text-xs font-semibold uppercase ${MEAL_COLORS[mealType]}`}>{mealType}</span>
                          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 mt-2">
                            {Object.entries(counters).map(([counter, dishes]) => (
                              <div key={counter} className="bg-gray-800/60 rounded-lg p-3">
                                <div className="text-xs font-bold text-white mb-1.5">{counter}</div>
                                <ul className="space-y-0.5">
                                  {dishes.map((d, i) => (
                                    <li key={i} className="text-xs text-gray-300 flex items-start gap-1">
                                      <span className="text-emerald-500 mt-0.5">•</span>{d}
                                    </li>
                                  ))}
                                </ul>
                              </div>
                            ))}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ))}
              </div>

              <div className="mt-4 flex gap-3">
                <Button size="sm" onClick={() => { clearAll(); setTab('view'); }}>View This Week's Menu</Button>
                <Button variant="ghost" size="sm" onClick={clearAll}>Upload More</Button>
              </div>
            </Card>
          )}
        </div>
      )}

      {/* View tab — grouped by counter */}
      {tab === 'view' && (
        <>
          <div className="flex items-center gap-3 mb-4">
            <button onClick={() => shiftWeek(-1)}
              className="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded-lg text-sm">← Prev</button>
            <span className="text-sm text-emerald-300 font-medium">{weekLabel()}</span>
            <button onClick={() => shiftWeek(1)}
              className="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded-lg text-sm">Next →</button>
          </div>

          {loading ? (
            <div className="text-center py-12 text-gray-400">Loading…</div>
          ) : Object.keys(grouped).length === 0 ? (
            <Card className="text-center py-10">
              <p className="text-gray-500 mb-3">No menus for this week.</p>
              <Button size="sm" onClick={() => setTab('upload')}>Upload Menu Images</Button>
            </Card>
          ) : (
            <div className="space-y-4">
              {Object.entries(grouped).sort(([a], [b]) => a.localeCompare(b)).map(([date, meals]) => (
                <Card key={date}>
                  <h2 className="font-semibold text-gray-200 mb-4">
                    {new Date(date + 'T00:00:00').toLocaleDateString('en-US', {
                      weekday: 'long', month: 'long', day: 'numeric'
                    })}
                  </h2>

                  {MEAL_ORDER.map(mealType => {
                    const counters = meals[mealType];
                    if (!counters) return null;
                    return (
                      <div key={mealType} className="mb-4 last:mb-0">
                        <div className="flex items-center gap-2 mb-2">
                          <span className={`text-xs font-semibold uppercase ${MEAL_COLORS[mealType]}`}>{mealType}</span>
                          <div className="flex-1 h-px bg-gray-700" />
                        </div>

                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                          {Object.entries(counters).map(([counter, menu]) => (
                            <div key={counter} className="bg-gray-900/50 rounded-lg p-3 group">
                              <div className="flex items-center justify-between mb-2">
                                <span className="text-xs font-bold text-white">{counter}</span>
                                <button onClick={() => handleDelete(menu.id)}
                                  className="text-xs text-red-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity">×</button>
                              </div>
                              <ul className="space-y-1">
                                {menu.dishes.map(d => (
                                  <li key={d.id} className="text-xs text-gray-400 flex items-center justify-between gap-1">
                                    <div className="flex items-center gap-1 min-w-0">
                                      <span className={d.calories > 0 ? 'text-emerald-500' : 'text-gray-600'}>●</span>
                                      <span className="truncate">{d.name}</span>
                                    </div>
                                    {d.calories > 0 && (
                                      <span className="text-gray-600 shrink-0 text-[10px]">{d.calories}kcal</span>
                                    )}
                                  </li>
                                ))}
                              </ul>
                            </div>
                          ))}
                        </div>
                      </div>
                    );
                  })}
                </Card>
              ))}
            </div>
          )}
        </>
      )}
    </div>
  );
}
