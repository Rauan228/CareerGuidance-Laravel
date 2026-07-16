from pathlib import Path
import re
from bs4 import BeautifulSoup

html = Path("output/vip_detail_astana-it-university.html").read_text(encoding="utf-8")
soup = BeautifulSoup(html, "lxml")

for cls in ["institution-specialty-list", "college-specialty-table-body", "college-qualification"]:
    nodes = soup.select(f".{cls}")
    print(f"=== .{cls} count={len(nodes)} ===")
    for i, n in enumerate(nodes[:3]):
        print(f"-- node {i} --")
        print(n.prettify()[:2500])
        print()

# also look for specialty cards structure near "Стоимость"
for el in soup.find_all(string=re.compile(r"Стоимость за год")):
    block = el
    for _ in range(8):
        if block.parent:
            block = block.parent
    print("=== cost ancestor ===")
    print(block.name, block.get("class"))
    print(block.prettify()[:2000])
    break
