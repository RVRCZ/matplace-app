"""python -m farm_agent --config config.yaml   |   --check tests the printers and the server once and exits."""
from __future__ import annotations

import argparse
import asyncio
import logging
import sys

from . import __version__
from .agent import Agent
from .config import load


async def check(agent: Agent) -> int:
    """One round without side effects on the printers: can we read them, does the server accept us?"""
    await agent.server.open()
    failed = 0
    try:
        for w in agent.workers.values():
            await w.driver.connect()
            s = await w.driver.status()
            print(f"printer {w.key:<24} {s.state:<9} {s.telemetry}")
            failed += s.state == "offline"
            await w.driver.close()
        try:
            answer = await agent.server.sync(__version__, [])
            known = set(answer.get("printers", []))
            print(f"server  {agent.config.server}: OK, printers assigned to this agent: {sorted(known) or 'none'}")
            for key in agent.workers:
                if key not in known:
                    print(f"  ! '{key}' is not assigned to this agent in the admin (or is disabled)")
                    failed += 1
        except Exception as e:  # noqa: BLE001
            print(f"server  {agent.config.server}: FAILED - {e}")
            failed += 1
    finally:
        await agent.server.close()
    return 1 if failed else 0


def main() -> None:
    ap = argparse.ArgumentParser(prog="farm_agent", description=f"matplace farm-agent {__version__}")
    ap.add_argument("--config", "-c", default="config.yaml")
    ap.add_argument("--check", action="store_true", help="test the configuration once and exit")
    args = ap.parse_args()
    try:
        config = load(args.config)
    except (OSError, ValueError, KeyError) as e:
        sys.exit(f"farm-agent: {e}")
    logging.basicConfig(level=getattr(logging, config.log_level, logging.INFO), format="%(asctime)s %(levelname)-7s %(name)s: %(message)s")
    agent = Agent(config)
    try:
        if args.check:
            sys.exit(asyncio.run(check(agent)))
        asyncio.run(agent.run())
    except KeyboardInterrupt:
        pass


if __name__ == "__main__":
    main()
