"""Compare all authored fields against an independent full native export after reload."""
import json, sys
from pathlib import Path
base=Path(__file__).parent
label=sys.argv[1]
a=json.loads((base/f'{label}-first-1.json').read_text())['elements']
b=json.loads((base/f'{label}-native-after-reload.json').read_text())['elements']
differences=[]
def compare(a,b,path=''):
    if isinstance(a,dict):
        if not isinstance(b,dict): differences.append(path+':type'); return
        for key,value in a.items():
            if key not in b: differences.append(path+'/'+key+':missing')
            else: compare(value,b[key],path+'/'+key)
    elif isinstance(a,list):
        if not isinstance(b,list): differences.append(path+':type'); return
        if len(a)!=len(b): differences.append(path+':length')
        for i,(x,y) in enumerate(zip(a,b)): compare(x,y,path+'/'+str(i))
    elif a!=b: differences.append(path+':changed')
compare(a,b)
result={'authored_field_differences':differences,'additional_native_defaults_allowed':True}
(base/f'{label}-native-comparison.json').write_text(json.dumps(result,indent=2)+'\n')
print(label, len(differences), 'authored field differences')
raise SystemExit(bool(differences))
