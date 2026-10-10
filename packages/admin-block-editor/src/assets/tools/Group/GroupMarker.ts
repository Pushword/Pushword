import { API, BlockAPI } from '@editorjs/editorjs'
import { GroupRegistry } from './GroupRegistry'

/**
 * What both markers of a group do as blocks: rendering, moving or removing one
 * changes the pairing, and a removed one takes its partner along.
 */
export default abstract class GroupMarker {
  protected api: API
  protected block: BlockAPI

  static get isReadOnlySupported(): boolean {
    return true
  }

  protected constructor({ api, block }: { api: API; block: BlockAPI }) {
    this.api = api
    this.block = block
  }

  rendered(): void {
    GroupRegistry.schedule(this.api)
  }

  moved(): void {
    GroupRegistry.schedule(this.api)
  }

  removed(): void {
    GroupRegistry.removePartnerOf(this.api, this.block.id)
    GroupRegistry.schedule(this.api)
  }
}
